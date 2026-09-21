<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Address;
use Chikolokoy08\PhToolkit\Internal\Accents;

it('uses the intl path when the extension is there', function (): void {
    expect(Accents::supportsNormalizer())->toBe(extension_loaded('intl'));
});

it('folds the same way with or without intl', function (string $input, string $expected): void {
    expect(Accents::foldWithNormalizer($input))->toBe($expected)
        ->and(Accents::foldWithTable($input))->toBe($expected);
})->with([
    ['Parañaque', 'paranaque'],
    ['PARAÑAQUE', 'paranaque'],
    ['Peñablanca', 'penablanca'],
    ['Sto. Niño', 'sto. nino'],
    ['Biñan', 'binan'],
    ['Dasmariñas', 'dasmarinas'],
    ['Las Piñas', 'las pinas'],
    ['Muñoz', 'munoz'],
    ['Café', 'cafe'],
    ['Málaga', 'malaga'],
    ['City of Cebu', 'city of cebu'],
    ['Lahug', 'lahug'],
    ['', ''],
]);

it('agrees on every name in the shipped dataset', function (): void {
    // The two paths only have to match on the data this package carries and on
    // what someone would type to search it. This checks the first half against
    // all 43,768 names rather than a sample.
    $mismatched = [];

    $names = [
        ...array_map(static fn ($region): string => $region->name, Address::getRegions()),
        ...array_map(static fn ($province): string => $province->name, Address::getProvinces()),
        ...array_map(static fn ($city): string => $city->name, Address::getCities(includeSubMunicipalities: true)),
    ];

    foreach (Address::getCities(includeSubMunicipalities: true) as $city) {
        foreach (Address::getBarangaysByCity($city->code) as $barangay) {
            $names[] = $barangay->name;
        }
    }

    foreach ($names as $name) {
        if (Accents::foldWithNormalizer($name) !== Accents::foldWithTable($name)) {
            $mismatched[] = $name;
        }
    }

    expect($mismatched)->toBe([])
        ->and(count($names))->toBeGreaterThan(43000);
});

it('folds every accented character the table covers the same way as intl', function (): void {
    $table = new ReflectionClassConstant(Accents::class, 'TABLE');

    /** @var array<string, string> $entries */
    $entries = $table->getValue();

    $mismatched = [];

    foreach (array_keys($entries) as $character) {
        if (Accents::foldWithNormalizer($character) !== Accents::foldWithTable($character)) {
            $mismatched[] = $character;
        }
    }

    expect($mismatched)->toBe([])
        ->and($entries)->not->toBe([]);
});

it('leaves a character outside the table alone without intl', function (): void {
    // Greek is not in the table and is not in the dataset. The intl path folds
    // the accent, the table path does not. Neither matches a Philippine place
    // name, so the search result is the same either way.
    expect(Accents::foldWithTable('Ά'))->toBe('ά')
        ->and(Accents::foldWithNormalizer('Ά'))->toBe('α');
});

it('still finds an accented city through the public search', function (): void {
    expect(array_map(
        static fn ($city): string => $city->name,
        Address::findCitiesByName('paranaque'),
    ))->toBe(['City of Parañaque']);
});
