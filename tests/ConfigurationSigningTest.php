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

namespace KevinGH\Box;

use Closure;
use DateTimeImmutable;
use Generator;
use Herrera\Annotations\Tokenizer;
use InvalidArgumentException;
use KevinGH\Box\Compactor\DummyCompactor;
use KevinGH\Box\Compactor\InvalidCompactor;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Compactor\PhpScoper;
use KevinGH\Box\Json\JsonValidationException;
use Phar;
use RuntimeException;
use Seld\JsonLint\ParsingException;
use stdClass;
use const DIRECTORY_SEPARATOR;
use const PHP_EOL;
use function abs;
use function array_fill_keys;
use function date_default_timezone_set;
use function file_put_contents;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\remove;
use function KevinGH\Box\FileSystem\rename;

/**
 * @covers \KevinGH\Box\Configuration
 * @covers \KevinGH\Box\MapFile
 */
class ConfigurationSigningTest extends ConfigurationTestCase
{
    public function test_the_default_signing_is_SHA1(): void
    {
        $this->assertSame(Phar::SHA1, $this->config->getSigningAlgorithm());

        $this->assertNull($this->config->getPrivateKeyPath());
        $this->assertNull($this->config->getPrivateKeyPassphrase());
        $this->assertFalse($this->config->promptForPrivateKey());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    /**
     * @dataProvider providePassFileFreeSigningAlgorithm
     */
    public function test_the_signing_algorithm_can_be_configured(string $algorithm, int $expected): void
    {
        $this->setConfig([
            'algorithm' => $algorithm,
        ]);

        $this->assertSame($expected, $this->config->getSigningAlgorithm());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_signing_algorithm_provided_must_be_valid(): void
    {
        try {
            $this->setConfig([
                'algorithm' => 'INVALID',
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The signing algorithm "INVALID" is not supported.',
                $exception->getMessage()
            );
        }
    }

    public function test_the_OpenSSL_algorithm_requires_a_private_key(): void
    {
        try {
            $this->setConfig([
                'algorithm' => 'OPENSSL',
            ]);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Expected to have a private key for OpenSSL signing but none have been provided.',
                $exception->getMessage()
            );
        }
    }

    /**
     * @dataProvider providePassFileFreeSigningAlgorithm
     */
    public function test_it_generates_a_warning_when_a_key_pass_is_provided_but_the_algorithm_is_not_OpenSSL(string $algorithm): void
    {
        $this->setConfig([
            'algorithm' => $algorithm,
            'key-pass' => true,
        ]);

        $this->assertNull($this->config->getPrivateKeyPassphrase());
        $this->assertFalse($this->config->promptForPrivateKey());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame(
            ['The setting "key-pass" has been set but ignored the signing algorithm is not "OPENSSL".'],
            $this->config->getWarnings()
        );

        foreach ([false, null] as $keyPass) {
            $this->setConfig([
                'algorithm' => $algorithm,
                'key-pass' => $keyPass,
            ]);

            $this->assertNull($this->config->getPrivateKeyPassphrase());
            $this->assertFalse($this->config->promptForPrivateKey());

            $this->assertSame(
                ['The setting "key-pass" has been set but is unnecessary since the signing algorithm is not "OPENSSL".'],
                $this->config->getRecommendations()
            );
            $this->assertSame([], $this->config->getWarnings());
        }
    }

    /**
     * @dataProvider providePassFileFreeSigningAlgorithm
     */
    public function test_it_generates_a_warning_when_a_key_path_is_provided_but_the_algorithm_is_not_OpenSSL(string $algorithm): void
    {
        touch('key-file');

        $this->setConfig([
            'algorithm' => $algorithm,
            'key' => 'key-file',
        ]);

        $this->assertSame(
            $this->tmp.'/key-file',
            $this->config->getPrivateKeyPath()
        );

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame(
            ['The setting "key" has been set but is ignored since the signing algorithm is not "OPENSSL".'],
            $this->config->getWarnings()
        );

        $this->setConfig([
            'algorithm' => $algorithm,
            'key' => null,
        ]);

        $this->assertNull($this->config->getPrivateKeyPath());

        $this->assertSame(
            ['The setting "key" has been set but is unnecessary since the signing algorithm is not "OPENSSL".'],
            $this->config->getRecommendations()
        );
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_key_can_be_configured(): void
    {
        touch('key-file');

        $this->setConfig([
            'algorithm' => 'OPENSSL',
            'key' => 'key-file',
        ]);

        $this->assertNull($this->config->getPrivateKeyPassphrase());
        $this->assertFalse($this->config->promptForPrivateKey());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());
    }

    public function test_the_key_pass_can_be_configured(): void
    {
        touch('key-file');

        $this->setConfig([
            'algorithm' => 'OPENSSL',
            'key' => 'key-file',
            'key-pass' => true,
        ]);

        $this->assertNull($this->config->getPrivateKeyPassphrase());
        $this->assertTrue($this->config->promptForPrivateKey());

        $this->assertSame([], $this->config->getRecommendations());
        $this->assertSame([], $this->config->getWarnings());

        foreach ([false, null] as $keyPass) {
            $this->setConfig([
                'algorithm' => 'OPENSSL',
                'key' => 'key-file',
                'key-pass' => $keyPass,
            ]);

            $this->assertNull($this->config->getPrivateKeyPassphrase());
            $this->assertFalse($this->config->promptForPrivateKey());

            $this->assertSame([], $this->config->getRecommendations());
            $this->assertSame([], $this->config->getWarnings());
        }
    }

    public function providePassFileFreeSigningAlgorithm(): Generator
    {
        yield ['MD5', Phar::MD5];
        yield ['SHA1', Phar::SHA1];
        yield ['SHA256', Phar::SHA256];
        yield ['SHA512', Phar::SHA512];
    }
}
