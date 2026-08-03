<?php

declare(strict_types=1);

if (2 !== $argc) {
    fwrite(\STDERR, "Usage: php .github/ci/check-package-archive.php <archive.zip>\n");

    exit(2);
}

$archivePath = $argv[1];
if (!is_file($archivePath)) {
    fwrite(\STDERR, sprintf("Archive not found: %s\n", $archivePath));

    exit(1);
}

$archive = new ZipArchive();
if (true !== $archive->open($archivePath)) {
    fwrite(\STDERR, sprintf("Unable to open ZIP archive: %s\n", $archivePath));

    exit(1);
}

try {
    $entries = [];
    for ($index = 0; $index < $archive->numFiles; ++$index) {
        $name = $archive->getNameIndex($index);
        if (false === $name) {
            fwrite(\STDERR, sprintf("Unable to read ZIP entry at index %d.\n", $index));

            exit(1);
        }

        $entries[] = ltrim(str_replace('\\', '/', $name), '/');
    }

    $composerEntries = array_values(array_filter(
        $entries,
        static fn (string $entry): bool => 'composer.json' === $entry || str_ends_with($entry, '/composer.json'),
    ));
    if (1 !== count($composerEntries)) {
        fwrite(\STDERR, sprintf("Expected exactly one composer.json, found %d.\n", count($composerEntries)));

        exit(1);
    }

    $composerEntry = $composerEntries[0];
    $rootPrefix = substr($composerEntry, 0, -strlen('composer.json'));
    $relativeEntries = [];
    foreach ($entries as $entry) {
        if (!str_starts_with($entry, $rootPrefix)) {
            fwrite(\STDERR, sprintf("ZIP entry is outside the package root: %s\n", $entry));

            exit(1);
        }

        $relativeEntries[] = substr($entry, strlen($rootPrefix));
    }

    $contains = static fn (string $path): bool => in_array($path, $relativeEntries, true);
    $containsDirectory = static function (string $directory) use ($relativeEntries): bool {
        foreach ($relativeEntries as $entry) {
            if (str_starts_with($entry, $directory.'/')) {
                return true;
            }
        }

        return false;
    };

    $requiredFiles = [
        'composer.json',
        'README.md',
        'CHANGELOG.md',
        'LICENSE',
        'docs/index.md',
        'docs/transactional-doctrine.md',
    ];
    foreach ($requiredFiles as $requiredFile) {
        if (!$contains($requiredFile)) {
            fwrite(\STDERR, sprintf("Required runtime file is missing: %s\n", $requiredFile));

            exit(1);
        }
    }

    foreach (['src', 'config'] as $requiredDirectory) {
        if (!$containsDirectory($requiredDirectory)) {
            fwrite(\STDERR, sprintf("Required runtime directory is missing or empty: %s/\n", $requiredDirectory));

            exit(1);
        }
    }

    $excludedFiles = [
        '.editorconfig',
        '.env',
        '.gitattributes',
        '.gitignore',
        'CODE_OF_CONDUCT.md',
        'CONTRIBUTING.md',
        'Makefile',
        'SECURITY.md',
        'phpunit.xml.dist',
        'phpunit.postgresql.xml.dist',
        'phpstan.neon.dist',
        '.php-cs-fixer.dist.php',
        'composer.lock',
    ];
    foreach ($excludedFiles as $excludedFile) {
        if ($contains($excludedFile)) {
            fwrite(\STDERR, sprintf("Development file must not be distributed: %s\n", $excludedFile));

            exit(1);
        }
    }

    foreach (['.github', 'tests', 'docker', 'vendor'] as $excludedDirectory) {
        if ($containsDirectory($excludedDirectory)) {
            fwrite(\STDERR, sprintf("Development directory must not be distributed: %s/\n", $excludedDirectory));

            exit(1);
        }
    }

    $normalizeRelativePath = static function (string $source, string $target): ?string {
        $sourceDirectory = str_contains($source, '/') ? dirname($source) : '';
        $candidate = '' === $sourceDirectory ? $target : $sourceDirectory.'/'.$target;
        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $candidate)) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                if ([] === $segments) {
                    return null;
                }
                array_pop($segments);

                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    };

    $markdownFiles = ['README.md', 'docs/index.md', 'docs/transactional-doctrine.md'];
    $validatedLinks = 0;
    foreach ($markdownFiles as $markdownFile) {
        $contents = $archive->getFromName($rootPrefix.$markdownFile);
        if (false === $contents) {
            fwrite(\STDERR, sprintf("Unable to read documentation from archive: %s\n", $markdownFile));

            exit(1);
        }

        preg_match_all('/(?<!!)\[[^]]*]\(([^)]+)\)/', $contents, $matches);
        foreach ($matches[1] as $rawTarget) {
            $target = trim($rawTarget);
            if (str_starts_with($target, '<') && str_ends_with($target, '>')) {
                $target = substr($target, 1, -1);
            }
            if (
                str_starts_with($target, 'http://')
                || str_starts_with($target, 'https://')
                || str_starts_with($target, 'mailto:')
                || str_starts_with($target, '#')
            ) {
                continue;
            }

            $target = preg_split('/[?#]/', $target, 2)[0];
            $resolvedTarget = $normalizeRelativePath($markdownFile, $target);
            if (null === $resolvedTarget) {
                fwrite(\STDERR, sprintf("Markdown link escapes package root: %s -> %s\n", $markdownFile, $rawTarget));

                exit(1);
            }
            if (!$contains($resolvedTarget)) {
                fwrite(\STDERR, sprintf("Broken relative Markdown link: %s -> %s\n", $markdownFile, $rawTarget));

                exit(1);
            }

            ++$validatedLinks;
        }
    }

    printf(
        "Package archive OK: %d entries, root prefix %s, runtime files and documentation present, %d relative Markdown links valid, development files excluded.\n",
        count($relativeEntries),
        '' === $rootPrefix ? '<none>' : $rootPrefix,
        $validatedLinks,
    );
} finally {
    $archive->close();
}
