<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Tests\Support;

use Chikolokoy08\PhToolkit\Psgc\Dataset;

/**
 * An in-memory dataset, so the lookups can be exercised on a small fixture
 * rather than the shipped 42,010 barangays.
 */
final class ArrayDataset implements Dataset
{
    /**
     * @param  list<array{0: string, 1: string}>  $regions
     * @param  list<array{0: string, 1: string, 2: string, 3: bool}>  $provinces
     * @param  list<array{0: string, 1: string, 2: string|null, 3: string, 4: string, 5: string|null}>  $cities
     * @param  list<array{0: string, 1: string, 2: string}>  $barangays
     */
    public function __construct(
        private readonly array $regions = [],
        private readonly array $provinces = [],
        private readonly array $cities = [],
        private readonly array $barangays = [],
        private readonly string $version = '2026-06-30',
    ) {
    }

    public function regions(): array
    {
        return $this->regions;
    }

    public function provinces(): array
    {
        return $this->provinces;
    }

    public function cities(): array
    {
        return $this->cities;
    }

    public function barangays(): array
    {
        return $this->barangays;
    }

    public function version(): string
    {
        return $this->version;
    }
}
