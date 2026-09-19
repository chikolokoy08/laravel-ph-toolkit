<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit;

use Chikolokoy08\PhToolkit\Internal\Separators;

/**
 * Philippine ZIP code validation.
 */
final class Zip
{
    private const PATTERN = '/\A\d{4}\z/';

    private function __construct()
    {
        //
    }

    /**
     * Checks whether a string is a valid Philippine ZIP code.
     *
     * Structural only: exactly four digits, with surrounding whitespace
     * ignored. Unlike mobile numbers and TINs, separators inside the code are
     * not stripped, because ZIP codes are not written in groups. The code is
     * not checked against the PhilPost directory.
     *
     * @example
     * Zip::isValidZipCode('6000'); // true, Cebu City
     */
    public static function isValidZipCode(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $trimmed = Separators::trim($value);

        return $trimmed !== null && preg_match(self::PATTERN, $trimmed) === 1;
    }
}
