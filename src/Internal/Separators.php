<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Internal;

/**
 * Shared character handling for the validators.
 *
 * @internal
 */
final class Separators
{
    /**
     * JavaScript's \s covers the Unicode space separators, the line and
     * paragraph separators, and the byte order mark, while PCRE's stops at
     * ASCII. The set is spelled out so both packages accept the same input,
     * which matters for numbers pasted out of a spreadsheet with non-breaking
     * spaces in them.
     */
    private const WHITESPACE = '\s\p{Z}\x{feff}';

    /**
     * Spaces, dashes, dots, and parentheses are presentation, not data, so
     * numbers are validated on their digits. Every other character survives
     * and fails the pattern it is checked against.
     *
     * Returns null for a string that is not valid UTF-8, which no caller can
     * do anything useful with.
     */
    public static function strip(string $value): ?string
    {
        return preg_replace('/['.self::WHITESPACE.'.()-]/u', '', $value);
    }

    /**
     * Trims the same whitespace set from both ends and nothing from the
     * middle. ZIP codes are not written in groups, so a space inside one is a
     * typo rather than formatting.
     */
    public static function trim(string $value): ?string
    {
        return preg_replace(
            '/\A['.self::WHITESPACE.']+|['.self::WHITESPACE.']+\z/u',
            '',
            $value,
        );
    }
}
