<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit;

use Chikolokoy08\PhToolkit\Exceptions\MissingIntlExtension;
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

    /**
     * Intl accepts 0 through 100 fraction digits and throws a RangeError
     * outside that, which ph-toolkit turns into null. ICU clamps instead of
     * refusing, so the bound is enforced here to keep the two packages
     * agreeing on what counts as out of range.
     */
    private const MAX_DECIMALS = 100;

    /**
     * A well formed BCP 47 language tag. Intl accepts any tag of this shape,
     * whether or not it names a locale anyone uses, and rejects everything
     * else. PHP's NumberFormatter is not consistent about that: PHP 8.2 takes
     * "not a locale" and quietly falls back to a default, where later versions
     * throw. Checking the shape first makes the answer the same on every
     * supported PHP version, and the same as the JavaScript package's.
     */
    private const LOCALE_PATTERN = '/\A
        [A-Za-z]{2,8}                                      # language
        (?:-[A-Za-z]{4})?                                  # script
        (?:-(?:[A-Za-z]{2}|[0-9]{3}))?                     # region
        (?:-(?:[0-9A-Za-z]{5,8}|[0-9][0-9A-Za-z]{3}))*     # variants
        (?:-[0-9A-WY-Za-wy-z](?:-[0-9A-Za-z]{2,8})+)*      # extensions
        (?:-[Xx](?:-[0-9A-Za-z]{1,8})+)?                   # private use
        \z/x';

    private function __construct()
    {
        //
    }

    /**
     * Formats a number as Philippine pesos, or returns null if the value is
     * not finite or the options are out of range.
     *
     * This is the one part of the package that needs the intl extension. It
     * throws MissingIntlExtension when that is not installed, because a
     * missing extension is a broken environment rather than bad input.
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
        // Checked before the arguments: a missing extension is a broken server
        // rather than a value the caller can correct, and reporting it only for
        // some inputs would hide it. extension_loaded is called unqualified on
        // purpose, as that is the seam the test for a machine without intl
        // shadows.
        if (! extension_loaded('intl')) {
            throw MissingIntlExtension::forPesoFormatting();
        }

        if ($value === null || ! is_finite((float) $value)) {
            return null;
        }

        if ($decimals < 0 || $decimals > self::MAX_DECIMALS) {
            return null;
        }

        if (preg_match(self::LOCALE_PATTERN, $locale) !== 1) {
            return null;
        }

        try {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        } catch (ValueError|IntlException) {
            // A well formed tag that this build of ICU does not know. Formatters
            // report bad input by returning null, so this is not allowed to
            // escape.
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
