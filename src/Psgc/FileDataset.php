<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Psgc;

use RuntimeException;

/**
 * Reads the generated PHP arrays in data/psgc.
 *
 * Each level is a separate file, required only when something asks for it, so
 * validating a phone number never parses the 42,010 barangays. Returning the
 * arrays straight from a `require` keeps them in opcache rather than costing a
 * parse per request.
 */
final class FileDataset implements Dataset
{
    public function __construct(private readonly string $directory)
    {
    }

    public function regions(): array
    {
        /**
         * bin/build-psgc.php writes these files in exactly this shape and
         * validates every parent link before writing them, so the shape is
         * asserted once here rather than re-checked on every lookup.
         *
         * @var list<array{0: string, 1: string}>
         */
        return $this->load('regions');
    }

    public function provinces(): array
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: bool}> */
        return $this->load('provinces');
    }

    public function cities(): array
    {
        /** @var list<array{0: string, 1: string, 2: string|null, 3: string, 4: string, 5: string|null}> */
        return $this->load('cities');
    }

    public function barangays(): array
    {
        /** @var list<array{0: string, 1: string, 2: string}> */
        return $this->load('barangays');
    }

    public function version(): string
    {
        /** @var array{release: string} $meta */
        $meta = $this->load('meta');

        return $meta['release'];
    }

    private function load(string $name): mixed
    {
        $path = $this->directory.'/'.$name.'.php';

        if (! is_file($path)) {
            throw new RuntimeException(
                "The PSGC data file {$path} is missing. Run bin/build-psgc.php to generate it, ".
                'or reinstall the package if you are seeing this from an installed copy.',
            );
        }

        return require $path;
    }
}
