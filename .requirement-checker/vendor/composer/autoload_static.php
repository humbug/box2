<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite2517a9d35e474f65ca523c5fca1c489
{
    public static $prefixLengthsPsr4 = array (
        '_' => 
        array (
            '_HumbugBoxbb220723f65b\\KevinGH\\RequirementChecker\\' => 50,
            '_HumbugBoxbb220723f65b\\Composer\\Semver\\' => 39,
        ),
    );

    public static $prefixDirsPsr4 = array (
        '_HumbugBoxbb220723f65b\\KevinGH\\RequirementChecker\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        '_HumbugBoxbb220723f65b\\Composer\\Semver\\' => 
        array (
            0 => __DIR__ . '/..' . '/composer/semver/src',
        ),
    );

    public static $classMap = array (
        '_HumbugBoxbb220723f65b\\Composer\\Semver\\Comparator' => __DIR__ . '/..' . '/composer/semver/src/Comparator.php',
        '_HumbugBoxbb220723f65b\\Composer\\Semver\\Constraint\\AbstractConstraint' => __DIR__ . '/..' . '/composer/semver/src/Constraint/AbstractConstraint.php',
        '_HumbugBoxbb220723f65b\\Composer\\Semver\\Constraint\\Constraint' => __DIR__ . '/..' . '/composer/semver/src/Constraint/Constraint.php',
        '_HumbugBoxbb220723f65b\\Composer\\Semver\\Constraint\\ConstraintInterface' => __DIR__ . '/..' . '/composer/semver/src/Constraint/ConstraintInterface.php',
        '_HumbugBoxbb220723f65b\\Composer\\Semver\\Constraint\\EmptyConstraint' => __DIR__ . '/..' . '/composer/semver/src/Constraint/EmptyConstraint.php',
        '_HumbugBoxbb220723f65b\\Composer\\Semver\\Constraint\\MultiConstraint' => __DIR__ . '/..' . '/composer/semver/src/Constraint/MultiConstraint.php',
        '_HumbugBoxbb220723f65b\\Composer\\Semver\\Semver' => __DIR__ . '/..' . '/composer/semver/src/Semver.php',
        '_HumbugBoxbb220723f65b\\Composer\\Semver\\VersionParser' => __DIR__ . '/..' . '/composer/semver/src/VersionParser.php',
        '_HumbugBoxbb220723f65b\\KevinGH\\RequirementChecker\\Checker' => __DIR__ . '/../..' . '/src/Checker.php',
        '_HumbugBoxbb220723f65b\\KevinGH\\RequirementChecker\\IO' => __DIR__ . '/../..' . '/src/IO.php',
        '_HumbugBoxbb220723f65b\\KevinGH\\RequirementChecker\\IsExtensionFulfilled' => __DIR__ . '/../..' . '/src/IsExtensionFulfilled.php',
        '_HumbugBoxbb220723f65b\\KevinGH\\RequirementChecker\\IsFulfilled' => __DIR__ . '/../..' . '/src/IsFulfilled.php',
        '_HumbugBoxbb220723f65b\\KevinGH\\RequirementChecker\\IsPhpVersionFulfilled' => __DIR__ . '/../..' . '/src/IsPhpVersionFulfilled.php',
        '_HumbugBoxbb220723f65b\\KevinGH\\RequirementChecker\\Printer' => __DIR__ . '/../..' . '/src/Printer.php',
        '_HumbugBoxbb220723f65b\\KevinGH\\RequirementChecker\\Requirement' => __DIR__ . '/../..' . '/src/Requirement.php',
        '_HumbugBoxbb220723f65b\\KevinGH\\RequirementChecker\\RequirementCollection' => __DIR__ . '/../..' . '/src/RequirementCollection.php',
        '_HumbugBoxbb220723f65b\\KevinGH\\RequirementChecker\\Terminal' => __DIR__ . '/../..' . '/src/Terminal.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite2517a9d35e474f65ca523c5fca1c489::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite2517a9d35e474f65ca523c5fca1c489::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInite2517a9d35e474f65ca523c5fca1c489::$classMap;

        }, null, ClassLoader::class);
    }
}
