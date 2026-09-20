<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Address;
use Illuminate\Foundation\Console\AboutCommand;

/**
 * Reads back what the provider registered, rather than scraping the rendered
 * table, so the assertions do not depend on how the command formats output.
 *
 * @return array<string, string>
 */
function phToolkitAboutSection(): array
{
    foreach ((new ReflectionProperty(AboutCommand::class, 'customDataResolvers'))->getValue() as $resolver) {
        $resolver();
    }

    /** @var array<string, list<mixed>> $data */
    $data = (new ReflectionProperty(AboutCommand::class, 'data'))->getValue();

    $section = [];

    foreach ($data['PH Toolkit'] ?? [] as $entry) {
        // add() stores a closure as-is and a plain array as label and value
        // pairs, so both shapes can turn up here.
        if (is_callable($entry)) {
            foreach ((array) $entry() as $label => $value) {
                $section[(string) $label] = (string) $value;
            }

            continue;
        }

        if (is_array($entry) && count($entry) === 2) {
            [$label, $value] = $entry;
            $section[(string) $label] = (string) (is_callable($value) ? $value() : $value);
        }
    }

    return $section;
}

it('adds a PH Toolkit section to php artisan about', function (): void {
    $this->artisan('about')
        ->expectsOutputToContain('PH Toolkit')
        ->assertExitCode(0);
});

it('reports the PSGC version in the rendered output', function (): void {
    $this->artisan('about --only=ph_toolkit')
        ->expectsOutputToContain(Address::psgcVersion())
        ->assertExitCode(0);
});

it('registers both the package version and the PSGC version', function (): void {
    $section = phToolkitAboutSection();

    expect($section)->toHaveKeys(['Version', 'PSGC Version'])
        ->and($section['PSGC Version'])->toBe('2026-06-30')
        ->and($section['PSGC Version'])->toBe(Address::psgcVersion());
});

it('reports whether the address routes are on', function (): void {
    expect(phToolkitAboutSection()['Address Routes'] ?? null)->toBe('disabled');
});

it('names the prefix when the address routes are on', function (): void {
    $this->withPackageConfig([
        'ph-toolkit.routes.enabled' => true,
        'ph-toolkit.routes.prefix' => 'api/psgc',
    ]);

    expect(phToolkitAboutSection()['Address Routes'] ?? null)->toBe('enabled at /api/psgc');
});

it('reports a package version rather than an empty string', function (): void {
    $version = phToolkitAboutSection()['Version'] ?? '';

    // Resolved from Composer's installed metadata, so in this repository it is
    // the root package's own version rather than a tagged release.
    expect($version)->not->toBe('')
        ->and($version)->toBeString();
});
