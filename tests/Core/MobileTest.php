<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Mobile;

dataset('valid mobile numbers', function (): array {
    $cases = [
        ['09171234567', '+639171234567'],
        ['0917 123 4567', '+639171234567'],
        ['0917-123-4567', '+639171234567'],
        ['+639171234567', '+639171234567'],
        ['+63 917 123 4567', '+639171234567'],
        ['+63-917-123-4567', '+639171234567'],
        ['639171234567', '+639171234567'],
        ['63 917 123 4567', '+639171234567'],
        ['  0917 123 4567  ', '+639171234567'],
        ['0905 555 1234', '+639055551234'],
        ['0926-123-4567', '+639261234567'],
        ['09181234567', '+639181234567'],
        ['0999 888 7766', '+639998887766'],
        ['0908 765 4321', '+639087654321'],
        ['0991 234 5678', '+639912345678'],
        ['09931234567', '+639931234567'],
    ];

    // Every known 08 block, in local, 63, and +63 form.
    foreach (['813', '817', '895', '896', '897', '898'] as $block) {
        $e164 = "+63{$block}1234567";

        $cases[] = ["0{$block}1234567", $e164];
        $cases[] = ["0{$block} 123 4567", $e164];
        $cases[] = ["0{$block}-123-4567", $e164];
        $cases[] = ["63{$block}1234567", $e164];
        $cases[] = ["+63{$block}1234567", $e164];
        $cases[] = ["+63 {$block} 123 4567", $e164];
    }

    return $cases;
});

dataset('mobile separator styles', [
    ['(0917) 123-4567', '+639171234567'],
    ['0917.123.4567', '+639171234567'],
    ['+63 (917) 123.4567', '+639171234567'],
    ['(0895) 123 4567', '+638951234567'],
]);

dataset('invalid mobile numbers', [
    'empty string' => [''],
    'whitespace only' => ['   '],
    'dashes only' => ['---'],
    'too short' => ['0917123456'],
    'too long' => ['091712345678'],
    'landline prefix' => ['0281234567'],
    'unlisted 08 prefix 0801' => ['08011234567'],
    'unlisted 08 prefix 0899' => ['08991234567'],
    'unlisted 08 prefix 0894' => ['08941234567'],
    'unlisted 08 prefix 0812' => ['08121234567'],
    'unlisted 08 prefix 0818' => ['08181234567'],
    'unlisted 08 prefix in +63 form' => ['+638011234567'],
    'listed 08 prefix, too short' => ['0895123456'],
    'listed 08 prefix, too long' => ['089512345678'],
    'missing leading zero' => ['9171234567'],
    'wrong country code' => ['+649171234567'],
    'zero after country code' => ['+630917123456'],
    'letters mixed in' => ['0917ABC4567'],
    'letters only' => ['abcdefghijk'],
    'plus in the middle' => ['0917+1234567'],
    'underscores as separators' => ['0917_123_4567'],
    'slashes as separators' => ['0917/123/4567'],
    'hash prefix' => ['#09171234567'],
    'trailing letters' => ['09171234567abc'],
    'truncated international' => ['+63917123456'],
    'double zero prefix' => ['00639171234567'],
]);

describe('isValidMobileNumber', function (): void {
    it('accepts a valid number', function (string $input): void {
        expect(Mobile::isValidMobileNumber($input))->toBeTrue();
    })->with('valid mobile numbers');

    it('ignores separators', function (string $input): void {
        expect(Mobile::isValidMobileNumber($input))->toBeTrue();
    })->with('mobile separator styles');

    it('rejects an invalid number', function (string $input): void {
        expect(Mobile::isValidMobileNumber($input))->toBeFalse();
    })->with('invalid mobile numbers');

    it('rejects null', function (): void {
        expect(Mobile::isValidMobileNumber(null))->toBeFalse();
    });
});

describe('formatMobileNumber', function (): void {
    it('normalizes a valid number to E.164', function (string $input, string $e164): void {
        expect(Mobile::formatMobileNumber($input))->toBe($e164);
    })->with('valid mobile numbers');

    it('normalizes a number written with separators', function (string $input, string $e164): void {
        expect(Mobile::formatMobileNumber($input))->toBe($e164);
    })->with('mobile separator styles');

    it('returns null for an invalid number', function (string $input): void {
        expect(Mobile::formatMobileNumber($input))->toBeNull();
    })->with('invalid mobile numbers');

    it('returns null for null', function (): void {
        expect(Mobile::formatMobileNumber(null))->toBeNull();
    });

    it('is idempotent', function (): void {
        $once = Mobile::formatMobileNumber('0917 123 4567');

        expect($once)->not->toBeNull()
            ->and(Mobile::formatMobileNumber($once))->toBe($once);
    });
});
