<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Mobile;
use Chikolokoy08\PhToolkit\Tin;
use Chikolokoy08\PhToolkit\Zip;

/*
 * PHP strings are byte strings, so a caller can hand these functions bytes
 * that are not valid UTF-8. JavaScript strings cannot be in that state, so
 * ph-toolkit has no equivalent case. Matching on the digits requires decoding
 * the input, which cannot be done here, so the value is treated as unusable
 * and reported the same way as any other invalid input: false from a
 * validator, null from a formatter.
 */
dataset('invalid utf-8', [
    // A lone continuation byte, which cannot start a sequence.
    'stray continuation byte' => ["0917\x80\x311234567"],
    // A truncated two-byte sequence.
    'truncated sequence' => ["0917\xC3"],
    // A valid sequence for a codepoint encoded in too many bytes.
    'overlong encoding' => ["0917\xC0\xAF1234567"],
    // The bytes of a UTF-16 surrogate half, which UTF-8 does not allow.
    'encoded surrogate half' => ["0917\xED\xA0\x801234567"],
    // Bytes that never appear in UTF-8 at all.
    'invalid bytes' => ["6000\xFE\xFF"],
]);

it('is not valid UTF-8 to begin with', function (string $input): void {
    expect(mb_check_encoding($input, 'UTF-8'))->toBeFalse();
})->with('invalid utf-8');

it('rejects invalid UTF-8 in isValidMobileNumber', function (string $input): void {
    expect(Mobile::isValidMobileNumber($input))->toBeFalse();
})->with('invalid utf-8');

it('returns null for invalid UTF-8 in formatMobileNumber', function (string $input): void {
    expect(Mobile::formatMobileNumber($input))->toBeNull();
})->with('invalid utf-8');

it('rejects invalid UTF-8 in isValidTin', function (string $input): void {
    expect(Tin::isValidTin($input))->toBeFalse();
})->with('invalid utf-8');

it('returns null for invalid UTF-8 in formatTin', function (string $input): void {
    expect(Tin::formatTin($input))->toBeNull();
})->with('invalid utf-8');

it('rejects invalid UTF-8 in isValidZipCode', function (string $input): void {
    expect(Zip::isValidZipCode($input))->toBeFalse();
})->with('invalid utf-8');

it('rejects invalid UTF-8 even when the digits alone would be valid', function (): void {
    // The digits spell a number that passes, so this fails on the encoding
    // rather than on the pattern.
    expect(Mobile::isValidMobileNumber("09171234567\xFF"))->toBeFalse()
        ->and(Mobile::formatMobileNumber("09171234567\xFF"))->toBeNull()
        ->and(Tin::isValidTin("123456789\xFF"))->toBeFalse()
        ->and(Tin::formatTin("123456789\xFF"))->toBeNull()
        ->and(Zip::isValidZipCode("6000\xFF"))->toBeFalse();
});
