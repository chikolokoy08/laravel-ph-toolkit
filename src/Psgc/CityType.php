<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Psgc;

/**
 * What the PSA calls a city-level entry. The values are the strings the
 * dataset and the JSON endpoints use.
 */
enum CityType: string
{
    case City = 'City';

    case Municipality = 'Municipality';

    case SubMunicipality = 'SubMunicipality';
}
