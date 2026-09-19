<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit;

use Chikolokoy08\PhToolkit\Internal\Separators;

/**
 * Philippine Tax Identification Number validation and formatting.
 */
final class Tin
{
    /**
     * 9 digits is the base TIN. 12 adds a 3-digit branch code, 14 adds the
     * 5-digit branch code used on newer BIR forms. Structural only; the BIR
     * publishes no check-digit rule.
     */
    private const PATTERN = '/\A(?:\d{9}|\d{12}|\d{14})\z/';

    private function __construct()
    {
        //
    }

    /**
     * Checks whether a string is a valid Philippine TIN.
     *
     * Accepts 9, 12, or 14 digits. A 9-digit TIN has no branch code; 12 and 14
     * digits include a 3- or 5-digit branch code.
     *
     * Spaces, dashes, dots, and parentheses are ignored, so '123-456-789',
     * '123.456.789', and '123 456 789' are all accepted. Letters and any other
     * character fail.
     *
     * @example
     * Tin::isValidTin('123-456-789-000'); // true
     */
    public static function isValidTin(?string $value): bool
    {
        return self::toDigits($value) !== null;
    }

    /**
     * Formats a Philippine TIN as XXX-XXX-XXX, XXX-XXX-XXX-XXX, or
     * XXX-XXX-XXX-XXXXX depending on its length, or returns null if it is not
     * a valid TIN. Spaces, dashes, dots, and parentheses in the input are
     * ignored, and surrounding whitespace is trimmed.
     *
     * @example
     * Tin::formatTin('123456789000'); // '123-456-789-000'
     */
    public static function formatTin(?string $value): ?string
    {
        $digits = self::toDigits($value);

        if ($digits === null) {
            return null;
        }

        $groups = [
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
        ];
        $branch = substr($digits, 9);

        if ($branch !== '') {
            $groups[] = $branch;
        }

        return implode('-', $groups);
    }

    private static function toDigits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = Separators::strip($value);

        if ($digits === null || preg_match(self::PATTERN, $digits) !== 1) {
            return null;
        }

        return $digits;
    }
}
