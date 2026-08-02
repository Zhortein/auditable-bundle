<?php

declare(strict_types=1);

use Composer\InstalledVersions;

require dirname(__DIR__, 2).'/vendor/autoload.php';

if (7 !== $argc) {
    fwrite(STDERR, "Usage: php .github/ci/assert-versions.php <php-minor> <symfony-minor> <doctrine-bundle-major> <doctrine-orm-major> <doctrine-dbal-major> <doctrine-persistence-major>\n");

    exit(2);
}

[, $expectedPhpMinor, $expectedSymfonyConstraint, $expectedDoctrineBundleMajor, $expectedDoctrineOrmMajor, $expectedDoctrineDbalMajor, $expectedDoctrinePersistenceMajor] = $argv;
$expectedSymfonyMinor = preg_replace('/\.\*$/', '', $expectedSymfonyConstraint);

if (null === $expectedSymfonyMinor) {
    fwrite(STDERR, "Invalid Symfony version constraint.\n");

    exit(2);
}

$packages = [
    'symfony/framework-bundle',
    'doctrine/doctrine-bundle',
    'doctrine/orm',
    'doctrine/dbal',
    'doctrine/persistence',
    'phpunit/phpunit',
    'phpstan/phpstan',
    'friendsofphp/php-cs-fixer',
];

printf("PHP: %s\n", PHP_VERSION);

foreach ($packages as $package) {
    printf("%s: %s\n", $package, InstalledVersions::getPrettyVersion($package) ?? 'unknown');
}

$assertVersion = static function (string $package, string $expectedPrefix): void {
    $version = InstalledVersions::getVersion($package);

    if (null === $version || !str_starts_with(ltrim($version, 'v'), $expectedPrefix.'.')) {
        fwrite(STDERR, sprintf(
            "Expected %s %s.x, resolved %s.\n",
            $package,
            $expectedPrefix,
            InstalledVersions::getPrettyVersion($package) ?? 'unknown',
        ));

        exit(1);
    }
};

$actualPhpMinor = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
if ($actualPhpMinor !== $expectedPhpMinor) {
    fwrite(STDERR, sprintf("Expected PHP %s.x, running %s.\n", $expectedPhpMinor, PHP_VERSION));

    exit(1);
}

$assertVersion('symfony/framework-bundle', $expectedSymfonyMinor);
$assertVersion('doctrine/doctrine-bundle', $expectedDoctrineBundleMajor);
$assertVersion('doctrine/orm', $expectedDoctrineOrmMajor);
$assertVersion('doctrine/dbal', $expectedDoctrineDbalMajor);
$assertVersion('doctrine/persistence', $expectedDoctrinePersistenceMajor);
