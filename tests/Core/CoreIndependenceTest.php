<?php

declare(strict_types=1);

it('keeps the core classes free of Illuminate', function (): void {
    $root = dirname(__DIR__, 2).'/src';

    $files = new RegexIterator(
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)),
        '/\.php$/',
    );

    $offenders = [];

    foreach ($files as $file) {
        $path = (string) $file;

        // The Laravel integration is allowed to use the framework. Everything
        // else has to work in a plain PHP project.
        if (str_starts_with($path, $root.'/Laravel/')) {
            continue;
        }

        if (str_contains((string) file_get_contents($path), 'Illuminate')) {
            $offenders[] = substr($path, strlen($root) + 1);
        }
    }

    expect($offenders)->toBe([]);
});
