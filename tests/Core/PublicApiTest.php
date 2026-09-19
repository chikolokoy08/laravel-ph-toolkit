<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Currency;
use Chikolokoy08\PhToolkit\Mobile;
use Chikolokoy08\PhToolkit\Tin;
use Chikolokoy08\PhToolkit\Zip;

it('exposes the documented methods and nothing else', function (string $class, array $expected): void {
    $methods = array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    sort($methods);

    expect($methods)->toBe($expected);
})->with([
    [Mobile::class, ['formatMobileNumber', 'isValidMobileNumber']],
    [Tin::class, ['formatTin', 'isValidTin']],
    [Zip::class, ['isValidZipCode']],
    [Currency::class, ['formatPeso']],
]);

it('cannot be instantiated', function (string $class): void {
    expect((new ReflectionClass($class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass($class))->getConstructor()?->isPrivate())->toBeTrue();
})->with([Mobile::class, Tin::class, Zip::class, Currency::class]);

it('rejects input that is not a string', function (callable $call): void {
    expect($call)->toThrow(TypeError::class);
})->with([
    // ph-toolkit funnels anything a JavaScript caller passes through a string
    // guard. PHP enforces the same contract in the signature, so these are
    // type errors rather than false and null.
    'int to isValidMobileNumber' => [fn (): bool => Mobile::isValidMobileNumber(42)],
    'array to isValidTin' => [fn (): bool => Tin::isValidTin([])],
    'bool to isValidZipCode' => [fn (): bool => Zip::isValidZipCode(true)],
    'string to formatPeso' => [fn (): ?string => Currency::formatPeso('1234.50')],
]);
