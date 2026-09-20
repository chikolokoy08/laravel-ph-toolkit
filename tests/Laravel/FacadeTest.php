<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Currency;
use Chikolokoy08\PhToolkit\Laravel\Facades\PhToolkit;
use Chikolokoy08\PhToolkit\Laravel\PhToolkit as Toolkit;
use Chikolokoy08\PhToolkit\Mobile;
use Chikolokoy08\PhToolkit\Tin;
use Chikolokoy08\PhToolkit\Zip;

it('resolves to a single shared instance', function (): void {
    expect(app(Toolkit::class))->toBeInstanceOf(Toolkit::class)
        ->and(app(Toolkit::class))->toBe(app(Toolkit::class));
});

it('proxies each core method to the class behind it', function (): void {
    expect(PhToolkit::isValidMobileNumber('0917 123 4567'))->toBe(Mobile::isValidMobileNumber('0917 123 4567'))
        ->and(PhToolkit::formatMobileNumber('(0917) 123-4567'))->toBe(Mobile::formatMobileNumber('(0917) 123-4567'))
        ->and(PhToolkit::isValidTin('123-456-789'))->toBe(Tin::isValidTin('123-456-789'))
        ->and(PhToolkit::formatTin('123456789000'))->toBe(Tin::formatTin('123456789000'))
        ->and(PhToolkit::isValidZipCode('6000'))->toBe(Zip::isValidZipCode('6000'))
        ->and(PhToolkit::formatPeso(1234.5))->toBe(Currency::formatPeso(1234.5));
});

it('returns the documented values', function (): void {
    expect(PhToolkit::isValidMobileNumber('0917 123 4567'))->toBeTrue()
        ->and(PhToolkit::formatMobileNumber('(0917) 123-4567'))->toBe('+639171234567')
        ->and(PhToolkit::formatMobileNumber('0917123'))->toBeNull()
        ->and(PhToolkit::isValidTin('123-456-789-000'))->toBeTrue()
        ->and(PhToolkit::formatTin('123456789000'))->toBe('123-456-789-000')
        ->and(PhToolkit::isValidZipCode('6000'))->toBeTrue()
        ->and(PhToolkit::formatPeso(1234.5))->toBe('₱1,234.50');
});

it('accepts named arguments through the facade', function (): void {
    expect(PhToolkit::formatPeso(1234.5, decimals: 0))->toBe('₱1,235')
        ->and(PhToolkit::formatPeso(1234.5, locale: 'fil-PH'))->toBe('₱1,234.50');
});

it('is registered under the PhToolkit alias', function (): void {
    expect(class_exists('PhToolkit'))->toBeTrue();
});

it('exposes exactly the six methods the npm package exports', function (): void {
    $methods = array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass(Toolkit::class))->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    sort($methods);

    expect($methods)->toBe([
        'formatMobileNumber',
        'formatPeso',
        'formatTin',
        'isValidMobileNumber',
        'isValidTin',
        'isValidZipCode',
    ]);
});

it('documents every method on the facade', function (): void {
    $docblock = (string) (new ReflectionClass(PhToolkit::class))->getDocComment();

    foreach ((new ReflectionClass(Toolkit::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        expect($docblock)->toContain(' '.$method->getName().'(');
    }
});
