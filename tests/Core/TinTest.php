<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Tin;

dataset('valid tins', [
    ['123456789', '123-456-789'],
    ['123-456-789', '123-456-789'],
    ['123 456 789', '123-456-789'],
    ['  123456789  ', '123-456-789'],
    ['123456789000', '123-456-789-000'],
    ['123-456-789-000', '123-456-789-000'],
    ['123 456 789 000', '123-456-789-000'],
    ['004327982001', '004-327-982-001'],
    ['12345678900000', '123-456-789-00000'],
    ['123-456-789-00000', '123-456-789-00000'],
    ['123 456 789 00000', '123-456-789-00000'],
    ['123.456.789', '123-456-789'],
    ['(123) 456-789', '123-456-789'],
]);

dataset('invalid tins', [
    'empty string' => [''],
    'whitespace only' => ['   '],
    'dashes only' => ['---------'],
    'eight digits' => ['12345678'],
    'ten digits' => ['1234567890'],
    'eleven digits' => ['12345678901'],
    'thirteen digits' => ['1234567890123'],
    'fifteen digits' => ['123456789000000'],
    'letters mixed in' => ['12345678A'],
    'letters only' => ['abcdefghi'],
    'leading plus' => ['+123456789'],
    'underscores as separators' => ['123_456_789'],
    'slashes as separators' => ['123/456/789'],
    'trailing letters' => ['123456789abc'],
]);

describe('isValidTin', function (): void {
    it('accepts a valid TIN', function (string $input): void {
        expect(Tin::isValidTin($input))->toBeTrue();
    })->with('valid tins');

    it('rejects an invalid TIN', function (string $input): void {
        expect(Tin::isValidTin($input))->toBeFalse();
    })->with('invalid tins');

    it('rejects null', function (): void {
        expect(Tin::isValidTin(null))->toBeFalse();
    });
});

describe('formatTin', function (): void {
    it('formats a valid TIN', function (string $input, string $formatted): void {
        expect(Tin::formatTin($input))->toBe($formatted);
    })->with('valid tins');

    it('returns null for an invalid TIN', function (string $input): void {
        expect(Tin::formatTin($input))->toBeNull();
    })->with('invalid tins');

    it('returns null for null', function (): void {
        expect(Tin::formatTin(null))->toBeNull();
    });

    it('is idempotent', function (): void {
        expect(Tin::formatTin('123-456-789-000'))->toBe('123-456-789-000');
    });
});
