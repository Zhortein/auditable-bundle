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
    'symfony/polyfill-mbstring',
    'phpunit/phpunit',
    'phpstan/phpstan',
    'friendsofphp/php-cs-fixer',
];

printf("PHP: %s\n", PHP_VERSION);

foreach ($packages as $package) {
    printf("%s: %s\n", $package, InstalledVersions::getPrettyVersion($package) ?? 'unknown');
}

if (!InstalledVersions::isInstalled('symfony/polyfill-mbstring')) {
    fwrite(STDERR, "symfony/polyfill-mbstring is not installed.\n");

    exit(1);
}

$nativeMbstring = extension_loaded('mbstring');
$iconv = extension_loaded('iconv');
$mbStrlen = function_exists('mb_strlen');
$mbSubstr = function_exists('mb_substr');

printf("Native mbstring: %s\n", $nativeMbstring ? 'loaded' : 'not loaded');
printf("Native iconv: %s\n", $iconv ? 'loaded' : 'not loaded');
printf("mb_strlen: %s\n", $mbStrlen ? 'available' : 'unavailable');
printf("mb_substr: %s\n", $mbSubstr ? 'available' : 'unavailable');

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

$rootComposer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$rootRequire = is_array($rootComposer) && isset($rootComposer['require']) && is_array($rootComposer['require']) ? $rootComposer['require'] : [];
if (!array_key_exists('symfony/polyfill-mbstring', $rootRequire)) {
    fwrite(STDERR, "symfony/polyfill-mbstring must be a direct runtime dependency.\n");

    exit(1);
}

$expectedNativeMbstring = getenv('EXPECT_NATIVE_MBSTRING');
if (!in_array($expectedNativeMbstring, ['true', 'false'], true)) {
    fwrite(STDERR, "EXPECT_NATIVE_MBSTRING must be true or false.\n");

    exit(2);
}

if (('true' === $expectedNativeMbstring) !== $nativeMbstring) {
    fwrite(STDERR, sprintf("Expected native mbstring %s, but it is %s.\n", $expectedNativeMbstring, $nativeMbstring ? 'loaded' : 'not loaded'));

    exit(1);
}

if (!$mbStrlen || !$mbSubstr) {
    fwrite(STDERR, "Multibyte string functions are unavailable.\n");

    exit(1);
}

if ('false' === $expectedNativeMbstring) {
    if (!$iconv || 3 !== mb_strlen('Été') || 'É' !== mb_substr('Été', 0, 1)) {
        fwrite(STDERR, "The mbstring polyfill path is not functional.\n");

        exit(1);
    }
}
