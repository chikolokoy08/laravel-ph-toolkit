<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Address;
use Chikolokoy08\PhToolkit\Psgc\CityType;

const NCR = '1300000000';
const REGION_VII = '0700000000';
const CEBU_PROVINCE = '0702200000';
const CITY_OF_CEBU = '0730600000';
const LAHUG = '0730600041';
const CITY_OF_MANILA = '1380600000';
const TONDO = '1380601000';

/** @param list<object{name: string}> $items */
function addressNames(array $items): array
{
    return array_map(static fn (object $item): string => $item->name, $items);
}

describe('dataset', function (): void {
    /*
     * These counts are exact on purpose. The PSA republishes the PSGC every
     * quarter, so they will fail on the next release. That is the signal to
     * rebuild the dataset and check the new numbers against the workbook
     * rather than a thing to loosen. See docs/updating-psgc.md.
     */
    it('reports the release date of the PSA publication', function (): void {
        expect(Address::psgcVersion())->toBe('2026-06-30');
    });

    it('has every region, province, city, and barangay', function (): void {
        expect(Address::getRegions())->toHaveCount(18)
            ->and(Address::getProvinces())->toHaveCount(84)
            ->and(Address::getCities())->toHaveCount(1642)
            ->and(Address::getCities(includeSubMunicipalities: true))->toHaveCount(1656);
    });

    it('puts every province in a region that exists', function (): void {
        foreach (Address::getProvinces() as $province) {
            expect(Address::getRegionByCode($province->regionCode))->not->toBeNull();
        }
    });

    it('puts every city in a region, and in a province when it has one', function (): void {
        foreach (Address::getCities(includeSubMunicipalities: true) as $city) {
            expect(Address::getRegionByCode($city->regionCode))->not->toBeNull();

            if ($city->provinceCode !== null) {
                expect(Address::getProvinceByCode($city->provinceCode)?->regionCode)->toBe($city->regionCode);
            }
        }
    });

    it('makes every province reachable from its region', function (): void {
        foreach (Address::getProvinces() as $province) {
            expect(Address::getProvincesByRegion($province->regionCode))->toContain($province);
        }
    });

    it('makes every city reachable from its region', function (): void {
        foreach (Address::getCities(includeSubMunicipalities: true) as $city) {
            expect(Address::getCitiesByRegion($city->regionCode, includeSubMunicipalities: true))->toContain($city);
        }
    });

    it('keeps codes unique across the whole dataset', function (): void {
        $codes = [
            ...array_map(static fn ($region): string => $region->code, Address::getRegions()),
            ...array_map(static fn ($province): string => $province->code, Address::getProvinces()),
            ...array_map(static fn ($city): string => $city->code, Address::getCities(includeSubMunicipalities: true)),
        ];

        expect(array_unique($codes))->toHaveCount(count($codes));
    });
});

describe('entries that hold a province code without being provinces', function (): void {
    it('has two of them, named as the PSA publishes them', function (): void {
        $notProvinces = array_filter(Address::getProvinces(), static fn ($province): bool => ! $province->isProvince);

        expect(addressNames(array_values($notProvinces)))
            ->toBe(['City of Isabela (Not a Province)', 'Special Geographic Area']);
    });

    it('marks every other province as a province', function (): void {
        $provinces = array_filter(Address::getProvinces(), static fn ($province): bool => $province->isProvince);

        expect($provinces)->toHaveCount(82);
    });

    it('lists it under its region with cities under it', function (string $name, string $code, string $regionCode, int $children): void {
        $province = Address::getProvinceByCode($code);

        expect($province?->name)->toBe($name)
            ->and($province?->isProvince)->toBeFalse()
            ->and(Address::getProvincesByRegion($regionCode))->toContain($province)
            ->and(Address::getCitiesByProvince($code))->toHaveCount($children);
    })->with([
        ['City of Isabela (Not a Province)', '0990100000', '0900000000', 1],
        ['Special Geographic Area', '1999900000', '1900000000', 8],
    ]);

    it('resolves City of Isabela through getCitiesByProvince', function (): void {
        expect(addressNames(Address::getCitiesByProvince('0990100000')))->toBe(['City of Isabela']);
    });

    it('resolves the Special Geographic Area municipalities', function (): void {
        $municipalities = Address::getCitiesByProvince('1999900000');

        expect(addressNames($municipalities))->toContain('Kapalawan')
            ->and(array_filter($municipalities, static fn ($city): bool => $city->type !== CityType::Municipality))
            ->toBe([]);
    });
});

describe('cities that sit directly under a region', function (): void {
    it('gives NCR cities but no provinces', function (): void {
        expect(Address::getProvincesByRegion(NCR))->toBe([])
            ->and(Address::getCitiesByRegion(NCR))->toHaveCount(17);
    });

    it('keeps a highly urbanized city off the province around it', function (): void {
        $cebuCity = Address::getCityByCode(CITY_OF_CEBU);

        expect($cebuCity?->name)->toBe('City of Cebu')
            ->and($cebuCity?->provinceCode)->toBeNull()
            ->and(array_map(static fn ($city): string => $city->code, Address::getCitiesByProvince(CEBU_PROVINCE)))
            ->not->toContain(CITY_OF_CEBU)
            ->and(array_map(static fn ($city): string => $city->code, Address::getCitiesByRegion(REGION_VII)))
            ->toContain(CITY_OF_CEBU);
    });

    it('keeps Baguio off Benguet', function (): void {
        expect(Address::getCityByCode('1430300000')?->provinceCode)->toBeNull();
    });

    it('keeps Zamboanga off the Isabela entry', function (): void {
        expect(Address::getCityByCode('0931700000')?->provinceCode)->toBeNull();
    });
});

describe('lookups against the real dataset', function (): void {
    it('finds a region by code', function (): void {
        expect(Address::getRegionByCode(REGION_VII)?->name)->toBe('Region VII (Central Visayas)');
    });

    it('finds a province by code', function (): void {
        expect(Address::getProvinceByCode(CEBU_PROVINCE)?->name)->toBe('Cebu');
    });

    it('lists the cities and municipalities of a province', function (): void {
        expect(Address::getCitiesByProvince(CEBU_PROVINCE))->toHaveCount(50);
    });

    it('lists the barangays of a city', function (): void {
        expect(Address::getBarangaysByCity(CITY_OF_CEBU))->toHaveCount(80);
    });

    it('finds a barangay by code', function (): void {
        $barangay = Address::getBarangayByCode(LAHUG);

        expect($barangay?->name)->toBe('Lahug')
            ->and($barangay?->cityCode)->toBe(CITY_OF_CEBU);
    });

    it('returns null for a code that does not exist', function (): void {
        expect(Address::getCityByCode('9999999999'))->toBeNull();
    });
});

describe('search against the real dataset', function (): void {
    it('finds the three Visayas regions', function (): void {
        expect(Address::findRegionsByName('visayas'))->toHaveCount(3);
    });

    it('finds both Camarines provinces', function (): void {
        expect(addressNames(Address::findProvincesByName('camarines')))
            ->toBe(['Camarines Norte', 'Camarines Sur']);
    });

    it('matches a name with a tilde when the query has none', function (): void {
        expect(addressNames(Address::findCitiesByName('paranaque')))->toBe(['City of Parañaque']);
    });

    it('scopes a barangay search to one city', function (): void {
        expect(addressNames(Address::findBarangaysByName('lahug', cityCode: CITY_OF_CEBU)))->toBe(['Lahug']);
    });

    it('searches every barangay when no city is given', function (): void {
        expect(count(Address::findBarangaysByName('poblacion')))->toBeGreaterThan(100);
    });
});

describe('sub-municipalities', function (): void {
    it('leaves sub-municipalities out of getCities by default', function (): void {
        $names = addressNames(Address::getCities());

        expect(array_filter(Address::getCities(), static fn ($city): bool => $city->type === CityType::SubMunicipality))
            ->toBe([])
            ->and($names)->not->toContain('Tondo I/II')
            ->and(addressNames(Address::getCities(includeSubMunicipalities: true)))->toContain('Tondo I/II');
    });

    it('shows the City of Manila but not its districts in a city list', function (): void {
        $cities = Address::getCitiesByRegion(NCR);

        expect(addressNames($cities))->toContain('City of Manila')
            ->and(addressNames($cities))->not->toContain('Tondo I/II')
            ->and(array_filter($cities, static fn ($city): bool => $city->type === CityType::SubMunicipality))
            ->toBe([]);
    });

    it('includes the districts when asked for', function (): void {
        $all = Address::getCitiesByRegion(NCR, includeSubMunicipalities: true);

        expect($all)->toHaveCount(31)
            ->and(addressNames($all))->toContain('Tondo I/II');
    });

    it('returns all 14 districts of Manila', function (): void {
        $districts = Address::getSubMunicipalitiesByCity(CITY_OF_MANILA);

        expect($districts)->toHaveCount(14)
            ->and(addressNames($districts))->toBe([
                'Tondo I/II', 'Binondo', 'Quiapo', 'San Nicolas', 'Santa Cruz', 'Sampaloc', 'San Miguel',
                'Ermita', 'Intramuros', 'Malate', 'Paco', 'Pandacan', 'Port Area', 'Santa Ana',
            ])
            ->and(array_filter($districts, static fn ($city): bool => $city->parentCityCode !== CITY_OF_MANILA))
            ->toBe([]);
    });

    it('gives every other city no sub-municipalities', function (): void {
        expect(Address::getSubMunicipalitiesByCity(CITY_OF_CEBU))->toBe([])
            ->and(array_filter(
                Address::getCities(includeSubMunicipalities: true),
                static fn ($city): bool => $city->parentCityCode !== null,
            ))->toHaveCount(14);
    });

    it("makes Manila's barangays the union of its districts'", function (): void {
        $union = [];

        foreach (Address::getSubMunicipalitiesByCity(CITY_OF_MANILA) as $district) {
            $union = [...$union, ...Address::getBarangaysByCity($district->code)];
        }

        expect($union)->toHaveCount(897)
            ->and(Address::getBarangaysByCity(CITY_OF_MANILA))->toBe($union);
    });

    it('lets a district answer for its own barangays', function (): void {
        $tondo = Address::getBarangaysByCity(TONDO);

        expect(count($tondo))->toBeGreaterThan(0)
            ->and(array_filter($tondo, static fn ($barangay): bool => $barangay->cityCode !== TONDO))->toBe([]);
    });

    it('resolves a Tondo barangay directly to its district', function (): void {
        $first = Address::getBarangaysByCity(TONDO)[0];

        expect(Address::getBarangayByCode($first->code)?->cityCode)->toBe(TONDO)
            ->and(Address::getCityByCode(TONDO)?->parentCityCode)->toBe(CITY_OF_MANILA);
    });

    it('gives a city that is not a sub-municipality a null parent', function (): void {
        expect(Address::getCityByCode(CITY_OF_CEBU)?->parentCityCode)->toBeNull();
    });
});
