<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit;

use IntlException;
use NumberFormatter;
use ValueError;

/**
 * Philippine peso formatting.
 */
final class Currency
{
    private const DEFAULT_LOCALE = 'en-PH';

    private const DEFAULT_DECIMALS = 2;

    private const CURRENCY = 'PHP';

    /** Intl accepts 0 through 100 fraction digits and rejects anything else. */
    private const MAX_DECIMALS = 100;

    private function __construct()
    {
        //
    }

    /**
     * Formats a number as Philippine pesos, or returns null if the value is
     * not finite or the options are out of range.
     *
     * @param  string  $locale  BCP 47 locale tag.
     * @param  int  $decimals  Fixed number of decimal places, 0 through 100.
     *
     * @example
     * Currency::formatPeso(1234.5); // '₱1,234.50'
     * Currency::formatPeso(1234.5, decimals: 0); // '₱1,235'
     * Currency::formatPeso(1234.5, locale: 'fil-PH'); // '₱1,234.50'
     */
    public static function formatPeso(
        int|float|null $value,
        string $locale = self::DEFAULT_LOCALE,
        int $decimals = self::DEFAULT_DECIMALS,
    ): ?string {
        if ($value === null || ! is_finite((float) $value)) {
            return null;
        }

        if ($decimals < 0 || $decimals > self::MAX_DECIMALS) {
            return null;
        }

        try {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        } catch (ValueError|IntlException) {
            // Intl rejects a malformed locale tag. Formatters report bad input
            // by returning null, so this is not allowed to escape.
            return null;
        }

        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        // ICU rounds half to even, Intl.NumberFormat rounds half away from
        // zero. Without this, 0.005 formats as ₱0.00 here and ₱0.01 in the
        // JavaScript package.
        $formatter->setAttribute(NumberFormatter::ROUNDING_MODE, NumberFormatter::ROUND_HALFUP);

        $formatted = $formatter->formatCurrency((float) $value, self::CURRENCY);

        return $formatted === false ? null : $formatted;
    }
}
