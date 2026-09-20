<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Laravel\PhToolkitServiceProvider;
use Illuminate\Support\ServiceProvider;

it('merges the package config', function (): void {
    expect(config('ph-toolkit.routes.enabled'))->toBeFalse()
        ->and(config('ph-toolkit.routes.prefix'))->toBe('ph-toolkit')
        ->and(config('ph-toolkit.routes.middleware'))->toBe(['throttle:60,1'])
        ->and(config('ph-toolkit.routes.cache_max_age'))->toBe(604800);
});

it('leaves the address routes off by default', function (): void {
    expect(config('ph-toolkit.routes.enabled'))->toBeFalse();
});

it('keeps sessions and CSRF out of the default middleware', function (): void {
    // Read-only JSON over bundled data: the web group would add cost and an
    // encryption key requirement for nothing.
    expect(config('ph-toolkit.routes.middleware'))->not->toContain('web');
});

it('publishes the config file', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        PhToolkitServiceProvider::class,
        'ph-toolkit-config',
    );

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('config/ph-toolkit.php')
        ->and(reset($paths))->toEndWith('config/ph-toolkit.php');
});

it('publishes the translations', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        PhToolkitServiceProvider::class,
        'ph-toolkit-translations',
    );

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('lang')
        ->and(reset($paths))->toEndWith('lang/vendor/ph-toolkit');
});

it('publishes the views', function (): void {
    $paths = ServiceProvider::pathsToPublish(PhToolkitServiceProvider::class, 'ph-toolkit-views');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('resources/views')
        ->and(reset($paths))->toEndWith('views/vendor/ph-toolkit');
});

it('keeps the shipped config in step with what the provider merges', function (): void {
    $shipped = require __DIR__.'/../../config/ph-toolkit.php';

    expect(array_keys($shipped))->toBe(['routes'])
        ->and(array_keys($shipped['routes']))->toBe(['enabled', 'prefix', 'middleware', 'cache_max_age']);
});
