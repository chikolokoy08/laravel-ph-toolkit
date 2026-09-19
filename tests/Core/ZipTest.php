<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Zip;

dataset('valid zip codes', [
    'Manila' => ['1000'],
    'Quezon City' => ['1101'],
    'Makati' => ['1200'],
    'Cebu City' => ['6000'],
    'Davao City' => ['8000'],
    'Iloilo City' => ['5000'],
    'Baguio' => ['2600'],
    'padded with whitespace' => ['  6000  '],
    'trailing space' => ['1000 '],
    'leading space' => [' 1000'],
    'surrounding tab and newline' => ["\t6000\n"],
    'leading zero' => ['0400'],
    'all zeros, valid by shape' => ['0000'],
]);

dataset('invalid zip codes', [
    'empty string' => [''],
    'whitespace only' => ['   '],
    'three digits' => ['100'],
    'five digits' => ['10000'],
    'internal space' => ['1 000'],
    'dash' => ['10-00'],
    'letters only' => ['abcd'],
    'letters mixed in' => ['100A'],
    'decimal' => ['1000.0'],
    'negative' => ['-1000'],
]);

describe('isValidZipCode', function (): void {
    it('accepts a valid ZIP code', function (string $input): void {
        expect(Zip::isValidZipCode($input))->toBeTrue();
    })->with('valid zip codes');

    it('rejects an invalid ZIP code', function (string $input): void {
        expect(Zip::isValidZipCode($input))->toBeFalse();
    })->with('invalid zip codes');

    it('rejects null', function (): void {
        expect(Zip::isValidZipCode(null))->toBeFalse();
    });
});
