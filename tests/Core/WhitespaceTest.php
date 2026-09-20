<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Currency;
use Chikolokoy08\PhToolkit\Mobile;
use Chikolokoy08\PhToolkit\Tin;
use Chikolokoy08\PhToolkit\Zip;

dataset('padding', [
    'spaces' => ['  %s  '],
    'leading tab' => ["\t%s"],
    'trailing newline' => ["%s\n"],
    'mixed' => ["\n %s \t"],
    // JavaScript's \s covers these and PCRE's does not, so they are the cases
    // that would drift between the two packages if the pattern were ASCII.
    'non-breaking spaces' => ["\u{00a0}%s\u{00a0}"],
    'byte order mark' => ["\u{feff}%s"],
]);

it('tolerates surrounding whitespace in isValidMobileNumber', function (string $pattern): void {
    expect(Mobile::isValidMobileNumber(sprintf($pattern, '09171234567')))->toBeTrue();
})->with('padding');

it('tolerates surrounding whitespace in formatMobileNumber', function (string $pattern): void {
    expect(Mobile::formatMobileNumber(sprintf($pattern, '09171234567')))->toBe('+639171234567');
})->with('padding');

it('tolerates surrounding whitespace in isValidTin', function (string $pattern): void {
    expect(Tin::isValidTin(sprintf($pattern, '123456789')))->toBeTrue();
})->with('padding');

it('tolerates surrounding whitespace in formatTin', function (string $pattern): void {
    expect(Tin::formatTin(sprintf($pattern, '123456789')))->toBe('123-456-789');
})->with('padding');

it('tolerates surrounding whitespace in isValidZipCode', function (string $pattern): void {
    expect(Zip::isValidZipCode(sprintf($pattern, '6000')))->toBeTrue();
})->with('padding');

it('does not coerce a numeric string in formatPeso', function (): void {
    // formatPeso takes a number, so there is no whitespace to trim. A numeric
    // string is invalid input rather than something to coerce, which the
    // signature enforces here instead of returning null as in JavaScript.
    expect(fn (): ?string => Currency::formatPeso(' 1234.50 '))
        ->toThrow(TypeError::class);
});
