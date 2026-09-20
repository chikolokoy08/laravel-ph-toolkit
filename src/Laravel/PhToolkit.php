<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Laravel;

use Chikolokoy08\PhToolkit\Currency;
use Chikolokoy08\PhToolkit\Mobile;
use Chikolokoy08\PhToolkit\Tin;
use Chikolokoy08\PhToolkit\Zip;

/**
 * What the PhToolkit facade resolves to.
 *
 * These are the same six methods the ph-toolkit npm package exports from its
 * root entry point, under the same names. The address lookups are not here:
 * they live on Address, which mirrors that package's separate entry point.
 */
final class PhToolkit
{
    /**
     * @see Mobile::isValidMobileNumber()
     *
     * @example
     * PhToolkit::isValidMobileNumber('(0917) 123-4567'); // true
     */
    public function isValidMobileNumber(?string $value): bool
    {
        return Mobile::isValidMobileNumber($value);
    }

    /**
     * @see Mobile::formatMobileNumber()
     *
     * @example
     * PhToolkit::formatMobileNumber('0895 123 4567'); // '+638951234567'
     */
    public function formatMobileNumber(?string $value): ?string
    {
        return Mobile::formatMobileNumber($value);
    }

    /**
     * @see Tin::isValidTin()
     *
     * @example
     * PhToolkit::isValidTin('123-456-789-000'); // true
     */
    public function isValidTin(?string $value): bool
    {
        return Tin::isValidTin($value);
    }

    /**
     * @see Tin::formatTin()
     *
     * @example
     * PhToolkit::formatTin('123456789000'); // '123-456-789-000'
     */
    public function formatTin(?string $value): ?string
    {
        return Tin::formatTin($value);
    }

    /**
     * @see Zip::isValidZipCode()
     *
     * @example
     * PhToolkit::isValidZipCode('6000'); // true, Cebu City
     */
    public function isValidZipCode(?string $value): bool
    {
        return Zip::isValidZipCode($value);
    }

    /**
     * @see Currency::formatPeso()
     *
     * @example
     * PhToolkit::formatPeso(1234.5); // '₱1,234.50'
     * PhToolkit::formatPeso(1234.5, decimals: 0); // '₱1,235'
     */
    public function formatPeso(int|float|null $value, string $locale = 'en-PH', int $decimals = 2): ?string
    {
        return Currency::formatPeso($value, $locale, $decimals);
    }
}
