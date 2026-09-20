<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Psgc;

/**
 * The raw PSGC tuples, one method per level so a caller that only needs
 * regions never pays for barangays.
 */
interface Dataset
{
    /**
     * @return list<array{0: string, 1: string}>
     */
    public function regions(): array;

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: bool}>
     */
    public function provinces(): array;

    /**
     * @return list<array{0: string, 1: string, 2: string|null, 3: string, 4: string, 5: string|null}>
     */
    public function cities(): array;

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function barangays(): array;

    /** The PSGC reference date of this dataset, as YYYY-MM-DD. */
    public function version(): string;
}
