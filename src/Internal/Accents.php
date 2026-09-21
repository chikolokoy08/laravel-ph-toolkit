<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Internal;

use Normalizer;

/**
 * Folds accents so a query and a place name match when they differ only by
 * diacritics.
 *
 * The intl extension does this properly, by decomposing to NFD and dropping
 * the combining marks. It is only suggested rather than required, so there is
 * a table for the Latin ranges to fall back on. The PSA dataset contains
 * exactly one non-ASCII character, n with a tilde, and Philippine place names
 * stay inside these ranges, so the two paths agree on everything this package
 * is asked about.
 *
 * @internal
 */
final class Accents
{
    /**
     * Latin-1 Supplement and Latin Extended-A, folded to ASCII. Enough for
     * Philippine and Spanish place names, which is what the dataset and its
     * queries are made of.
     *
     * Only letters that carry a combining mark are here. Stroked letters such
     * as d with stroke and l with stroke are left out, because NFD does not
     * decompose them either, and this table has to agree with what the intl
     * path does.
     */
    private const TABLE = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I',
        'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i',
        'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
        'Ā' => 'A', 'ā' => 'a', 'Ă' => 'A', 'ă' => 'a', 'Ą' => 'A', 'ą' => 'a',
        'Ć' => 'C', 'ć' => 'c', 'Ĉ' => 'C', 'ĉ' => 'c', 'Ċ' => 'C', 'ċ' => 'c', 'Č' => 'C', 'č' => 'c',
        'Ď' => 'D', 'ď' => 'd',
        'Ē' => 'E', 'ē' => 'e', 'Ĕ' => 'E', 'ĕ' => 'e', 'Ė' => 'E', 'ė' => 'e',
        'Ę' => 'E', 'ę' => 'e', 'Ě' => 'E', 'ě' => 'e',
        'Ĝ' => 'G', 'ĝ' => 'g', 'Ğ' => 'G', 'ğ' => 'g', 'Ġ' => 'G', 'ġ' => 'g', 'Ģ' => 'G', 'ģ' => 'g',
        'Ĥ' => 'H', 'ĥ' => 'h', 'Ĩ' => 'I', 'ĩ' => 'i', 'Ī' => 'I', 'ī' => 'i', 'Ĭ' => 'I', 'ĭ' => 'i',
        'Į' => 'I', 'į' => 'i', 'İ' => 'I', 'Ĵ' => 'J', 'ĵ' => 'j', 'Ķ' => 'K', 'ķ' => 'k',
        'Ĺ' => 'L', 'ĺ' => 'l', 'Ļ' => 'L', 'ļ' => 'l', 'Ľ' => 'L', 'ľ' => 'l',
        'Ń' => 'N', 'ń' => 'n', 'Ņ' => 'N', 'ņ' => 'n', 'Ň' => 'N', 'ň' => 'n',
        'Ō' => 'O', 'ō' => 'o', 'Ŏ' => 'O', 'ŏ' => 'o', 'Ő' => 'O', 'ő' => 'o',
        'Ŕ' => 'R', 'ŕ' => 'r', 'Ŗ' => 'R', 'ŗ' => 'r', 'Ř' => 'R', 'ř' => 'r',
        'Ś' => 'S', 'ś' => 's', 'Ŝ' => 'S', 'ŝ' => 's', 'Ş' => 'S', 'ş' => 's', 'Š' => 'S', 'š' => 's',
        'Ţ' => 'T', 'ţ' => 't', 'Ť' => 'T', 'ť' => 't',
        'Ũ' => 'U', 'ũ' => 'u', 'Ū' => 'U', 'ū' => 'u', 'Ŭ' => 'U', 'ŭ' => 'u',
        'Ů' => 'U', 'ů' => 'u', 'Ű' => 'U', 'ű' => 'u', 'Ų' => 'U', 'ų' => 'u',
        'Ŵ' => 'W', 'ŵ' => 'w', 'Ŷ' => 'Y', 'ŷ' => 'y', 'Ÿ' => 'Y',
        'Ź' => 'Z', 'ź' => 'z', 'Ż' => 'Z', 'ż' => 'z', 'Ž' => 'Z', 'ž' => 'z',
    ];

    public static function fold(string $value): string
    {
        return self::supportsNormalizer()
            ? self::foldWithNormalizer($value)
            : self::foldWithTable($value);
    }

    public static function supportsNormalizer(): bool
    {
        return extension_loaded('intl');
    }

    /** Decomposes to NFD and drops the combining marks, as the JavaScript package does. */
    public static function foldWithNormalizer(string $value): string
    {
        $decomposed = Normalizer::normalize($value, Normalizer::FORM_D);

        // Normalization fails on input that is not valid UTF-8. Searching the
        // raw bytes still behaves sensibly, so the original is kept.
        if (! is_string($decomposed)) {
            $decomposed = $value;
        }

        $stripped = preg_replace('/\p{Mn}+/u', '', $decomposed);

        return self::lower($stripped ?? $decomposed);
    }

    public static function foldWithTable(string $value): string
    {
        return self::lower(strtr($value, self::TABLE));
    }

    private static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }
}
