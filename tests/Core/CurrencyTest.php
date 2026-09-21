<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Currency;

describe('formatPeso', function (): void {
    it('formats an amount in pesos', function (int|float $value, string $expected): void {
        expect(Currency::formatPeso($value))->toBe($expected);
    })->with([
        [1234.5, '₱1,234.50'],
        [0, '₱0.00'],
        [-500, '-₱500.00'],
        [1234567.891, '₱1,234,567.89'],
        [99, '₱99.00'],
        [0.005, '₱0.01'],
    ]);

    it('rounds to whole pesos when decimals is 0', function (): void {
        expect(Currency::formatPeso(1234.5, decimals: 0))->toBe('₱1,235');
    });

    it('keeps extra decimals when asked', function (): void {
        expect(Currency::formatPeso(1234.5, decimals: 4))->toBe('₱1,234.5000');
    });

    it('accepts another locale', function (): void {
        expect(Currency::formatPeso(1234.5, locale: 'fil-PH'))->toBe('₱1,234.50');
    });

    it('returns null for a value that is not finite', function (float $value): void {
        expect(Currency::formatPeso($value))->toBeNull();
    })->with([
        'NaN' => [NAN],
        'Infinity' => [INF],
        '-Infinity' => [-INF],
    ]);

    it('returns null for null', function (): void {
        expect(Currency::formatPeso(null))->toBeNull();
    });

    it('formats at the highest decimal count Intl allows', function (): void {
        expect(Currency::formatPeso(1234.5, decimals: 100))
            ->toBe('₱1,234.5'.str_repeat('0', 99));
    });

    it('returns null for a decimal count out of range', function (int $decimals): void {
        expect(Currency::formatPeso(1234.5, decimals: $decimals))->toBeNull();
    })->with([
        'negative decimals' => [-1],
        'more than Intl allows' => [101],
    ]);

    it('returns null for a malformed locale tag', function (string $locale): void {
        // The shape is checked before the tag reaches Intl, because PHP 8.2
        // formats with a fallback locale here where later versions throw.
        expect(Currency::formatPeso(1234.5, locale: $locale))->toBeNull();
    })->with([
        'words' => ['not a locale'],
        'underscore instead of dash' => ['en_PH'],
        'empty' => [''],
        'digits' => ['123'],
        'one letter' => ['a'],
        'trailing dash' => ['en-'],
        'double dash' => ['en--PH'],
        'subtag too long' => ['en-PHILIPPINES'],
    ]);

    it('accepts the tags a Philippine application actually uses', function (string $locale): void {
        expect(Currency::formatPeso(1234.5, locale: $locale))->toBe('₱1,234.50');
    })->with([
        'en-PH' => ['en-PH'],
        'fil-PH' => ['fil-PH'],
        'ceb-PH' => ['ceb-PH'],
        'tl' => ['tl'],
        'case insensitive' => ['EN-ph'],
        'with an extension' => ['en-PH-u-nu-latn'],
    ]);
});
