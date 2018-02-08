<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\Box\Console\Command;

use Assert\Assertion;
use KevinGH\Box\Box;
use KevinGH\Box\Compactor;
use KevinGH\Box\Configuration;
use KevinGH\Box\Console\Logger\BuildLogger;
use KevinGH\Box\MapFile;
use KevinGH\Box\StubGenerator;
use RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use function KevinGH\Box\FileSystem\chmod;
use function KevinGH\Box\FileSystem\remove;
use function KevinGH\Box\formatted_filesize;
use function KevinGH\Box\get_phar_compression_algorithms;

final class Build extends Configurable
{
    use ChangeableWorkingDirectory;

    private const HELP = <<<'HELP'
The <info>%command.name%</info> command will build a new PHAR based on a variety of settings.
<comment>
  This command relies on a configuration file for loading
  PHAR packaging settings. If a configuration file is not
  specified through the <info>--config|-c</info> option, one of
  the following files will be used (in order): <info>box.json</info>,
  <info>box.json.dist</info>
</comment>
The configuration file is actually a JSON object saved to a file. For more
information check the documentation online:
<comment>
  https://github.com/humbug/box
</comment>
HELP;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName('build');
        $this->setDescription('Builds a new PHAR');
        $this->setHelp(self::HELP);

        $this->configureWorkingDirOption();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $this->changeWorkingDirectory($input);

        $io = new SymfonyStyle($input, $output);

        $io->writeln($this->getApplication()->getHelp());
        $io->writeln('');

        $config = $this->getConfig($input);
        $path = $config->getOutputPath();

        $logger = new BuildLogger($io);

        $startTime = microtime(true);

        $this->loadBootstrapFile($config, $logger);
        $this->removeExistingPhar($config, $logger);

        $logger->logStartBuilding($path);

        $this->createPhar($path, $config, $input, $output, $logger);

        $this->correctPermissions($path, $config, $logger);

        $logger->log(
            BuildLogger::STAR_PREFIX,
            'Done.'
        );

        if ($io->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
            $io->comment(
                sprintf(
                    "<info>Size: %s\nMemory usage: %.2fMB (peak: %.2fMB), time: %.2fs<info>",
                    formatted_filesize($path),
                    round(memory_get_usage() / 1024 / 1024, 2),
                    round(memory_get_peak_usage() / 1024 / 1024, 2),
                    round(microtime(true) - $startTime, 2)
                )
            );
        }

        if (false === file_exists($path)) {
            //TODO: check that one
            $io->warning('The archive was not generated because it did not have any contents');
        }
    }

    private function createPhar(
        string $path,
        Configuration $config,
        InputInterface $input,
        OutputInterface $output,
        BuildLogger $logger
    ): void {
        $box = Box::create($path);

        $box->getPhar()->startBuffering();

        $this->setReplacementValues($config, $box, $logger);
        $this->registerCompactors($config, $box, $logger);
        $this->registerFileMapping($config, $box, $logger);

        $this->addFiles($config, $box, $logger);

        $main = $this->registerMainScript($config, $box, $logger);

        $this->registerStub($config, $box, $main, $logger);
        $this->configureMetadata($config, $box, $logger);
        $this->configureCompressionAlgorithm($config, $box, $logger);

        $box->getPhar()->stopBuffering();

        $this->signPhar($config, $box, $path, $input, $output, $logger);
    }

    private function loadBootstrapFile(Configuration $config, BuildLogger $logger): void
    {
        $file = $config->getBootstrapFile();

        if (null === $file) {
            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            sprintf(
                'Loading the bootstrap file "%s"',
                $file
            )
        );

        $config->loadBootstrap();
    }

    private function removeExistingPhar(Configuration $config, BuildLogger $logger): void
    {
        $path = $config->getOutputPath();

        if (false === file_exists($path)) {
            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            sprintf(
                'Removing the existing PHAR "%s"',
                $path
            )
        );

        remove($path);
    }

    private function setReplacementValues(Configuration $config, Box $box, BuildLogger $logger): void
    {
        $values = $config->getProcessedReplacements();

        if ([] === $values) {
            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            'Setting replacement values'
        );

        foreach ($values as $key => $value) {
            $logger->log(
                BuildLogger::PLUS_PREFIX,
                sprintf(
                    '%s: %s',
                    $key,
                    $value
                )
            );
        }

        $box->registerPlaceholders($values);
    }

    private function registerCompactors(Configuration $config, Box $box, BuildLogger $logger): void
    {
        $compactors = $config->getCompactors();

        if ([] === $compactors) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'No compactor to register'
            );

            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            'Registering compactors'
        );

        $logCompactors = function (Compactor $compactor) use ($logger): void {
            $logger->log(
                BuildLogger::PLUS_PREFIX,
                get_class($compactor)
            );
        };

        array_map($logCompactors, $compactors);

        $box->registerCompactors($compactors);
    }

    private function registerFileMapping(Configuration $config, Box $box, BuildLogger $logger): void
    {
        $fileMapper = $config->getFileMapper();

        $this->logMap($fileMapper, $logger);

        $box->registerFileMapping(
            $config->getBasePathRetriever(),
            $fileMapper
        );
    }

    private function addFiles(Configuration $config, Box $box, BuildLogger $logger): void
    {
        $logger->log(BuildLogger::QUESTION_MARK_PREFIX, 'Adding binary files');

        $count = count($config->getBinaryFiles());

        $box->addFiles($config->getBinaryFiles(), true);

        $logger->log(
            BuildLogger::CHEVRON_PREFIX,
            0 === $count
                ? 'No file found'
                : sprintf('%d file(s)', $count)
        );

        $logger->log(BuildLogger::QUESTION_MARK_PREFIX, 'Adding files');

        $count = count($config->getFiles());

        $box->addFiles($config->getFiles(), false);

        $logger->log(
            BuildLogger::CHEVRON_PREFIX,
            0 === $count
                ? 'No file found'
                : sprintf('%d file(s)', $count)
        );
    }

    private function registerMainScript(Configuration $config, Box $box, BuildLogger $logger): ?string
    {
        $main = $config->getMainScriptPath();

        if (null === $main) {
            return null;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            sprintf(
                'Adding main file: %s',
                $config->getBasePath().DIRECTORY_SEPARATOR.$main
            )
        );

        $localMain = $box->addFile(
            $main,
            $config->getMainScriptContent()
        );

        if ($localMain !== $main) {
            $logger->log(
                BuildLogger::CHEVRON_PREFIX,
                $localMain
            );
        }

        return $localMain;
    }

    private function registerStub(Configuration $config, Box $box, ?string $main, BuildLogger $logger): void
    {
        if (true === $config->isStubGenerated()) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'Generating new stub'
            );

            $stub = $this->createStub($config, $main, $logger);

            $box->getPhar()->setStub($stub->generate());
        } elseif (null !== ($stub = $config->getStubPath())) {
            $stub = $config->getBasePath().DIRECTORY_SEPARATOR.$stub;

            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                sprintf(
                    'Using stub file: %s',
                    $stub
                )
            );

            $box->registerStub($stub);
        } else {
            $aliasWasAdded = $box->getPhar()->setAlias($config->getAlias());

            Assertion::true(
                $aliasWasAdded,
                sprintf(
                    'The alias "%s" is invalid. See Phar::setAlias() documentation for more information.',
                    $config->getAlias()
                )
            );

            if (null !== $main) {
                $box->getPhar()->setDefaultStub($main, $main);
            }

            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'Using default stub'
            );
        }
    }

    private function configureMetadata(Configuration $config, Box $box, BuildLogger $logger): void
    {
        if (null !== ($metadata = $config->getMetadata())) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                'Setting metadata'
            );

            $logger->log(
                BuildLogger::MINUS_PREFIX,
                is_string($metadata) ? $metadata : var_export($metadata, true)
            );

            $box->getPhar()->setMetadata($metadata);
        }
    }

    private function configureCompressionAlgorithm(Configuration $config, Box $box, BuildLogger $logger): void
    {
        if (null !== ($algorithm = $config->getCompressionAlgorithm())) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                sprintf(
                    'Compressing with the algorithm "<comment>%s</comment>"',
                    array_search($algorithm, get_phar_compression_algorithms(), true)
                )
            );

            $box->getPhar()->compressFiles($algorithm);
        } else {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                '<error>No compression</error>'
            );
        }
    }

    private function signPhar(
        Configuration $config,
        Box $box,
        string $path,
        InputInterface $input,
        OutputInterface $output,
        BuildLogger $logger
    ): void {
        // sign using private key, if applicable
        //TODO: check that out
        remove($path.'.pubkey');

        $key = $config->getPrivateKeyPath();

        if (null === $key) {
            if (null !== ($algorithm = $config->getSigningAlgorithm())) {
                $box->getPhar()->setSignatureAlgorithm($algorithm);
            }

            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            'Signing using a private key'
        );

        $passphrase = $config->getPrivateKeyPassphrase();

        if ($config->isPrivateKeyPrompt()) {
            if (false === $input->isInteractive()) {
                throw new RuntimeException(
                    sprintf(
                        'Accessing to the private key "%s" requires a passphrase but none provided. Either '
                        .'provide one or run this command in interactive mode.',
                        $key
                    )
                );
            }

            /** @var $dialog QuestionHelper */
            $dialog = $this->getHelper('question');

            $question = new Question('Private key passphrase:');
            $question->setHidden(false);
            $question->setHiddenFallback(false);

            $passphrase = $dialog->ask($input, $output, $question);

            $output->writeln('');
        }

        $box->signUsingFile($key, $passphrase);
    }

    private function correctPermissions(string $path, Configuration $config, BuildLogger $logger): void
    {
        if (null !== ($chmod = $config->getFileMode())) {
            $logger->log(
                BuildLogger::QUESTION_MARK_PREFIX,
                "Setting file permissions to <comment>$chmod</comment>"
            );

            chmod($path, $chmod);
        }
    }

    private function createStub(Configuration $config, ?string $main, BuildLogger $logger): StubGenerator
    {
        $stub = StubGenerator::create()
            ->alias($config->getAlias())
            ->extract($config->isExtractable())
            ->index($main)
            ->intercept($config->isInterceptFileFuncs())
            ->mimetypes($config->getMimetypeMapping())
            ->mung($config->getMungVariables())
            ->notFound($config->getNotFoundScriptPath())
            ->web($config->isWebPhar())
        ;

        if (null !== ($shebang = $config->getShebang())) {
            $logger->log(
                BuildLogger::MINUS_PREFIX,
                sprintf(
                    'Using custom shebang line: %s',
                    $shebang
                ),
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );

            $stub->shebang($shebang);
        }

        if (null !== ($banner = $config->getStubBanner())) {
            $logger->log(
                BuildLogger::MINUS_PREFIX,
                sprintf(
                    'Using custom banner: %s',
                    $banner
                ),
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );

            $stub->banner($banner);
        } elseif (null !== ($banner = $config->getStubBannerFromFile())) {
            $logger->log(
                BuildLogger::MINUS_PREFIX,
                sprintf(
                    'Using custom banner from file: %s',
                    $config->getBasePath().DIRECTORY_SEPARATOR.$config->getStubBannerPath()
                ),
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );

            $stub->banner($banner);
        }

        return $stub;
    }

    private function logMap(MapFile $fileMapper, BuildLogger $logger): void
    {
        $map = $fileMapper->getMap();

        if ([] === $map) {
            return;
        }

        $logger->log(
            BuildLogger::QUESTION_MARK_PREFIX,
            'Mapping paths'
        );

        foreach ($map as $item) {
            foreach ($item as $match => $replace) {
                if (empty($match)) {
                    $match = '(all)';
                }

                $logger->log(
                    BuildLogger::MINUS_PREFIX,
                    sprintf(
                        '%s <info>></info> %s',
                        $match,
                        $replace
                    )
                );
            }
        }
    }
}
