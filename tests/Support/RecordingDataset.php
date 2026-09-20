<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Tests\Support;

use Chikolokoy08\PhToolkit\Psgc\Dataset;

/**
 * Wraps a dataset and records which levels were actually asked for, so a test
 * can assert that a lookup did not reach for data it does not need.
 */
final class RecordingDataset implements Dataset
{
    /** @var list<string> */
    private array $requested = [];

    public function __construct(private readonly Dataset $inner)
    {
    }

    public function regions(): array
    {
        $this->requested[] = 'regions';

        return $this->inner->regions();
    }

    public function provinces(): array
    {
        $this->requested[] = 'provinces';

        return $this->inner->provinces();
    }

    public function cities(): array
    {
        $this->requested[] = 'cities';

        return $this->inner->cities();
    }

    public function barangays(): array
    {
        $this->requested[] = 'barangays';

        return $this->inner->barangays();
    }

    public function version(): string
    {
        $this->requested[] = 'version';

        return $this->inner->version();
    }

    /** @return list<string> */
    public function requested(): array
    {
        return array_values(array_unique($this->requested));
    }
}
