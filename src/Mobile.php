<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit;

use Chikolokoy08\PhToolkit\Internal\Separators;

/**
 * Philippine mobile number validation and normalization.
 */
final class Mobile
{
    /**
     * The known mobile blocks outside the 09 range, from public prefix
     * directories: DITO launched on 0895 through 0898 in 2021, and Smart and
     * Globe hold the legacy 0813 and 0817 blocks. Written without the trunk
     * zero because the pattern matches the local, 63, and +63 forms at once.
     * Update this list if the NTC assigns new 08 ranges.
     */
    private const EIGHT_SERIES = '813|817|895|896|897|898';

    /**
     * Within those blocks the check is structural. Number portability means a
     * prefix no longer identifies a network, so there is nothing further to
     * check against. \A and \z rather than ^ and $, because PCRE's $ also
     * matches before a trailing newline and JavaScript's does not.
     */
    private const PATTERN = '/\A(?:\+?63|0)(?:9\d{9}|(?:'.self::EIGHT_SERIES.')\d{7})\z/';

    private function __construct()
    {
        //
    }

    /**
     * Checks whether a string is a valid Philippine mobile number.
     *
     * Accepts the 09 range and the known 08 blocks (0813, 0817, 0895 through
     * 0898), each in local, 63, and +63 form. Spaces, dashes, dots, and
     * parentheses are ignored, so numbers are checked on their digits. Letters
     * and any other character fail.
     *
     * @example
     * Mobile::isValidMobileNumber('(0917) 123-4567'); // true
     */
    public static function isValidMobileNumber(?string $value): bool
    {
        return self::toE164($value) !== null;
    }

    /**
     * Normalizes a Philippine mobile number to E.164, or returns null if it is
     * not a valid number. Spaces, dashes, dots, and parentheses in the input
     * are ignored, and surrounding whitespace is trimmed.
     *
     * @example
     * Mobile::formatMobileNumber('0895 123 4567'); // '+638951234567'
     */
    public static function formatMobileNumber(?string $value): ?string
    {
        return self::toE164($value);
    }

    private static function toE164(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $compact = Separators::strip($value);

        if ($compact === null || preg_match(self::PATTERN, $compact) !== 1) {
            return null;
        }

        return '+63'.substr($compact, -10);
    }
}
