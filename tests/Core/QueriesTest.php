<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Psgc\AddressIndex;
use Chikolokoy08\PhToolkit\Psgc\Queries;
use Chikolokoy08\PhToolkit\Tests\Support\ArrayDataset;

/*
 * A small stand-in for the real dataset, shaped like it but not drawn from it.
 * The names are real places; the codes are made up for these tests and do not
 * match the PSGC. It covers the awkward parts: a region with no provinces, an
 * entry that holds a province code without being a province, a city with no
 * province, and a name with a tilde.
 */
function psgcFixture(): AddressIndex
{
    return new AddressIndex(new ArrayDataset(
        regions: [
            ['0700000000', 'Region VII (Central Visayas)'],
            ['1300000000', 'National Capital Region (NCR)'],
        ],
        provinces: [
            ['0701200000', 'Bohol', '0700000000', true],
            ['0702200000', 'Cebu', '0700000000', true],
            ['0799900000', 'Special Area (Not a Province)', '0700000000', false],
        ],
        cities: [
            ['0701201000', 'Tagbilaran', '0701200000', '0700000000', 'City', null],
            ['0702201000', 'Argao', '0702200000', '0700000000', 'Municipality', null],
            ['0730600000', 'City of Cebu', null, '0700000000', 'City', null],
            ['0799901000', 'Lone Municipality', '0799900000', '0700000000', 'Municipality', null],
            ['1381000000', 'City of Parañaque', null, '1300000000', 'City', null],
            ['1380600000', 'City of Manila', null, '1300000000', 'City', null],
            ['1380601000', 'Tondo I/II', null, '1300000000', 'SubMunicipality', '1380600000'],
            ['1380609000', 'Intramuros', null, '1300000000', 'SubMunicipality', '1380600000'],
        ],
        barangays: [
            ['0730600041', 'Lahug', '0730600000'],
            ['0730600042', 'Mabolo', '0730600000'],
            ['0702201001', 'Poblacion', '0702201000'],
            ['1381000001', 'Baclaran', '1381000000'],
            ['1380601001', 'Tondo I/II Barangay 1', '1380601000'],
        ],
    ));
}

/** @param list<object{name: string}> $items */
function nameList(array $items): array
{
    return array_map(static fn (object $item): string => $item->name, $items);
}

describe('lookups by code', function (): void {
    it('leaves sub-municipalities out of getCities by default', function (): void {
        expect(nameList(Queries::getCities(psgcFixture())))->toBe([
            'Tagbilaran', 'Argao', 'City of Cebu', 'Lone Municipality', 'City of Parañaque', 'City of Manila',
        ]);
    });

    it('can include sub-municipalities in getCities', function (): void {
        expect(Queries::getCities(psgcFixture(), includeSubMunicipalities: true))->toHaveCount(8);
    });

    it('returns every region', function (): void {
        expect(nameList(Queries::getRegions(psgcFixture())))
            ->toBe(['Region VII (Central Visayas)', 'National Capital Region (NCR)']);
    });

    it('finds a region by code', function (): void {
        expect(Queries::getRegionByCode(psgcFixture(), '0700000000')?->name)->toBe('Region VII (Central Visayas)');
    });

    it('finds a province by code', function (): void {
        expect(Queries::getProvinceByCode(psgcFixture(), '0702200000')?->name)->toBe('Cebu');
    });

    it('finds a city by code', function (): void {
        expect(Queries::getCityByCode(psgcFixture(), '0730600000')?->type->value)->toBe('City');
    });

    it('finds a barangay by code', function (): void {
        expect(Queries::getBarangayByCode(psgcFixture(), '0730600041')?->name)->toBe('Lahug');
    });

    it('returns null for an unknown code', function (string $method): void {
        expect(Queries::$method(psgcFixture(), '9999999999'))->toBeNull();
    })->with(['getRegionByCode', 'getProvinceByCode', 'getCityByCode', 'getBarangayByCode']);

    it('returns null for a null code', function (string $method): void {
        expect(Queries::$method(psgcFixture(), null))->toBeNull();
    })->with(['getRegionByCode', 'getProvinceByCode', 'getCityByCode', 'getBarangayByCode']);
});

describe('lookups by parent', function (): void {
    it('returns the provinces of a region', function (): void {
        expect(nameList(Queries::getProvincesByRegion(psgcFixture(), '0700000000')))
            ->toBe(['Bohol', 'Cebu', 'Special Area (Not a Province)']);
    });

    it('is empty for a region with no provinces', function (): void {
        expect(Queries::getProvincesByRegion(psgcFixture(), '1300000000'))->toBe([]);
    });

    it('returns the cities of a province', function (): void {
        expect(nameList(Queries::getCitiesByProvince(psgcFixture(), '0702200000')))->toBe(['Argao']);
    });

    it('keeps cities reachable under an entry that is not a province', function (): void {
        expect(nameList(Queries::getCitiesByProvince(psgcFixture(), '0799900000')))->toBe(['Lone Municipality']);
    });

    it('leaves sub-municipalities out of getCitiesByRegion by default', function (): void {
        expect(nameList(Queries::getCitiesByRegion(psgcFixture(), '1300000000')))
            ->toBe(['City of Parañaque', 'City of Manila']);
    });

    it('can include sub-municipalities in getCitiesByRegion', function (): void {
        expect(nameList(Queries::getCitiesByRegion(psgcFixture(), '1300000000', includeSubMunicipalities: true)))
            ->toBe(['City of Parañaque', 'City of Manila', 'Tondo I/II', 'Intramuros']);
    });

    it('returns the districts of a city', function (): void {
        expect(nameList(Queries::getSubMunicipalitiesByCity(psgcFixture(), '1380600000')))
            ->toBe(['Tondo I/II', 'Intramuros']);
    });

    it('contributes nothing for a district with no barangays of its own', function (): void {
        expect(Queries::getBarangaysByCity(psgcFixture(), '1380609000'))->toBe([]);
    });

    it('is empty for a city with no sub-municipalities', function (): void {
        expect(Queries::getSubMunicipalitiesByCity(psgcFixture(), '0730600000'))->toBe([]);
    });

    it("answers with its districts' barangays for a city that has none of its own", function (): void {
        expect(nameList(Queries::getBarangaysByCity(psgcFixture(), '1380600000')))->toBe(['Tondo I/II Barangay 1']);
    });

    it('lets a district answer for its own barangays', function (): void {
        expect(nameList(Queries::getBarangaysByCity(psgcFixture(), '1380601000')))->toBe(['Tondo I/II Barangay 1']);
    });

    it('includes cities that have no province', function (): void {
        expect(nameList(Queries::getCitiesByRegion(psgcFixture(), '0700000000')))
            ->toBe(['Tagbilaran', 'Argao', 'City of Cebu', 'Lone Municipality']);
    });

    it('returns the barangays of a city', function (): void {
        expect(nameList(Queries::getBarangaysByCity(psgcFixture(), '0730600000')))->toBe(['Lahug', 'Mabolo']);
    });

    it('returns an empty array for an unknown code', function (string $method): void {
        expect(Queries::$method(psgcFixture(), '9999999999'))->toBe([]);
    })->with(['getProvincesByRegion', 'getCitiesByProvince', 'getCitiesByRegion', 'getBarangaysByCity']);

    it('returns an empty array for a null code', function (string $method): void {
        expect(Queries::$method(psgcFixture(), null))->toBe([]);
    })->with(['getProvincesByRegion', 'getCitiesByProvince', 'getCitiesByRegion', 'getBarangaysByCity']);
});

describe('results are copies', function (): void {
    it('does not let a caller change the next call by mutating a list', function (): void {
        $index = psgcFixture();
        $first = Queries::getRegions($index);
        array_pop($first);

        expect(Queries::getRegions($index))->toHaveCount(2);
    });

    it('does not let a caller change the next call by mutating a group', function (): void {
        $index = psgcFixture();
        $first = Queries::getBarangaysByCity($index, '0730600000');
        $first = [];

        expect(Queries::getBarangaysByCity($index, '0730600000'))->toHaveCount(2)
            ->and($first)->toBe([]);
    });
});

describe('search by name', function (): void {
    it('matches part of a name', function (): void {
        expect(nameList(Queries::findCitiesByName(psgcFixture(), 'cebu')))->toBe(['City of Cebu']);
    });

    it('ignores case', function (): void {
        expect(nameList(Queries::findProvincesByName(psgcFixture(), 'CEBU')))->toBe(['Cebu']);
    });

    it('ignores accents in the query', function (): void {
        expect(nameList(Queries::findCitiesByName(psgcFixture(), 'paranaque')))->toBe(['City of Parañaque']);
    });

    it('ignores accents in the data', function (): void {
        expect(nameList(Queries::findCitiesByName(psgcFixture(), 'parañaque')))->toBe(['City of Parañaque']);
    });

    it('finds regions', function (): void {
        expect(Queries::findRegionsByName(psgcFixture(), 'visayas'))->toHaveCount(1);
    });

    it('finds barangays across every city', function (): void {
        expect(Queries::findBarangaysByName(psgcFixture(), 'a'))->toHaveCount(5);
    });

    it('scopes barangays to one city', function (): void {
        expect(Queries::findBarangaysByName(psgcFixture(), 'a', cityCode: '0730600000'))->toHaveCount(2);
    });

    it('returns nothing for an unknown city scope', function (): void {
        expect(Queries::findBarangaysByName(psgcFixture(), 'a', cityCode: '9999999999'))->toBe([]);
    });

    it('returns nothing for an empty query', function (?string $query): void {
        $index = psgcFixture();

        expect(Queries::findCitiesByName($index, $query))->toBe([])
            ->and(Queries::findRegionsByName($index, $query))->toBe([])
            ->and(Queries::findProvincesByName($index, $query))->toBe([])
            ->and(Queries::findBarangaysByName($index, $query))->toBe([]);
    })->with(['empty' => [''], 'whitespace' => ['   '], 'null' => [null]]);

    it('trims the query', function (): void {
        expect(nameList(Queries::findCitiesByName(psgcFixture(), '  cebu  ')))->toBe(['City of Cebu']);
    });

    it('returns nothing when nothing matches', function (): void {
        expect(Queries::findCitiesByName(psgcFixture(), 'reykjavik'))->toBe([]);
    });
});
