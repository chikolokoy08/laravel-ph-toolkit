<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Laravel\Facades;

use Chikolokoy08\PhToolkit\Laravel\PhToolkit as Toolkit;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isValidMobileNumber(?string $value)
 * @method static string|null formatMobileNumber(?string $value)
 * @method static bool isValidTin(?string $value)
 * @method static string|null formatTin(?string $value)
 * @method static bool isValidZipCode(?string $value)
 * @method static string|null formatPeso(int|float|null $value, string $locale = 'en-PH', int $decimals = 2)
 *
 * @see Toolkit
 */
final class PhToolkit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Toolkit::class;
    }
}
