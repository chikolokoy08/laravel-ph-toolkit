<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit;

use Chikolokoy08\PhToolkit\Psgc\AddressIndex;
use Chikolokoy08\PhToolkit\Psgc\Barangay;
use Chikolokoy08\PhToolkit\Psgc\City;
use Chikolokoy08\PhToolkit\Psgc\FileDataset;
use Chikolokoy08\PhToolkit\Psgc\Province;
use Chikolokoy08\PhToolkit\Psgc\Queries;
use Chikolokoy08\PhToolkit\Psgc\Region;

/**
 * Lookups over the Philippine Standard Geographic Code.
 *
 * Each level is read from disk the first time something asks for it, so
 * listing regions never loads the 42,010 barangays.
 */
final class Address
{
    private static ?AddressIndex $index = null;

    private function __construct()
    {
        //
    }

    /**
     * Returns all 18 regions, in PSGC order.
     *
     * @return list<Region>
     *
     * @example
     * Address::getRegions()[6]->name; // 'Region VII (Central Visayas)'
     */
    public static function getRegions(): array
    {
        return Queries::getRegions(self::index());
    }

    /**
     * Looks up a region by its PSGC code, or returns null if there is no match.
     *
     * @example
     * Address::getRegionByCode('0700000000'); // Region VII (Central Visayas)
     */
    public static function getRegionByCode(?string $code): ?Region
    {
        return Queries::getRegionByCode(self::index(), $code);
    }

    /**
     * Returns every province, including the two PSA entries whose isProvince
     * is false. See the Province class for what those are.
     *
     * @return list<Province>
     *
     * @example
     * count(array_filter(Address::getProvinces(), fn ($province) => $province->isProvince)); // 82
     */
    public static function getProvinces(): array
    {
        return Queries::getProvinces(self::index());
    }

    /**
     * Looks up a province by its PSGC code, or returns null if there is no
     * match.
     *
     * @example
     * Address::getProvinceByCode('0702200000'); // Cebu
     */
    public static function getProvinceByCode(?string $code): ?Province
    {
        return Queries::getProvinceByCode(self::index(), $code);
    }

    /**
     * Returns the provinces of a region, in PSGC order. Empty for NCR, which
     * has no provinces: use getCitiesByRegion there.
     *
     * @return list<Province>
     *
     * @example
     * Address::getProvincesByRegion('0700000000'); // Bohol and Cebu
     */
    public static function getProvincesByRegion(?string $regionCode): array
    {
        return Queries::getProvincesByRegion(self::index(), $regionCode);
    }

    /**
     * Returns every city and municipality in the country. Sub-municipalities
     * are left out unless you ask for them, so this returns 1,642 entries by
     * default and 1,656 with the argument.
     *
     * @return list<City>
     *
     * @example
     * count(Address::getCities()); // 1642
     * count(Address::getCities(includeSubMunicipalities: true)); // 1656
     */
    public static function getCities(bool $includeSubMunicipalities = false): array
    {
        return Queries::getCities(self::index(), $includeSubMunicipalities);
    }

    /**
     * Looks up a city or municipality by its PSGC code, or returns null if
     * there is no match.
     *
     * @example
     * Address::getCityByCode('0730600000'); // City of Cebu
     */
    public static function getCityByCode(?string $code): ?City
    {
        return Queries::getCityByCode(self::index(), $code);
    }

    /**
     * Returns the cities and municipalities of a province, in PSGC order.
     * Sub-municipalities are left out unless you ask for them.
     *
     * @return list<City>
     *
     * @example
     * count(Address::getCitiesByProvince('0702200000')); // 50, the cities and municipalities of Cebu
     */
    public static function getCitiesByProvince(
        ?string $provinceCode,
        bool $includeSubMunicipalities = false,
    ): array {
        return Queries::getCitiesByProvince(self::index(), $provinceCode, $includeSubMunicipalities);
    }

    /**
     * Returns every city and municipality in a region, whether or not it sits
     * under a province. This is how to list the cities of NCR.
     *
     * Sub-municipalities are left out unless you ask for them, so Metro Manila
     * lists the City of Manila once rather than followed by its 14 districts.
     *
     * @return list<City>
     *
     * @example
     * count(Address::getCitiesByRegion('1300000000')); // 17, the cities of Metro Manila
     * count(Address::getCitiesByRegion('1300000000', includeSubMunicipalities: true)); // 31
     */
    public static function getCitiesByRegion(
        ?string $regionCode,
        bool $includeSubMunicipalities = false,
    ): array {
        return Queries::getCitiesByRegion(self::index(), $regionCode, $includeSubMunicipalities);
    }

    /**
     * Returns the sub-municipalities of a city, in PSGC order. Only the City
     * of Manila has any; every other city returns an empty array. Use this for
     * an optional district step in an address form.
     *
     * @return list<City>
     *
     * @example
     * count(Address::getSubMunicipalitiesByCity('1380600000')); // 14, the districts of Manila
     */
    public static function getSubMunicipalitiesByCity(?string $cityCode): array
    {
        return Queries::getSubMunicipalitiesByCity(self::index(), $cityCode);
    }

    /**
     * Looks up a barangay by its PSGC code, or returns null if there is no
     * match.
     *
     * @example
     * Address::getBarangayByCode('0730600041'); // Lahug, City of Cebu
     */
    public static function getBarangayByCode(?string $code): ?Barangay
    {
        return Queries::getBarangayByCode(self::index(), $code);
    }

    /**
     * Returns the barangays of a city or municipality, in PSGC order.
     *
     * The City of Manila holds no barangays directly, so this returns all 897
     * from its 14 sub-municipalities. Passing a sub-municipality code returns
     * only that district's barangays.
     *
     * @return list<Barangay>
     *
     * @example
     * count(Address::getBarangaysByCity('0730600000')); // 80, the barangays of City of Cebu
     */
    public static function getBarangaysByCity(?string $cityCode): array
    {
        return Queries::getBarangaysByCity(self::index(), $cityCode);
    }

    /**
     * Finds regions whose name contains the query. Case- and
     * accent-insensitive. An empty query returns an empty array.
     *
     * @return list<Region>
     *
     * @example
     * Address::findRegionsByName('visayas'); // Regions VI, VII, and VIII
     */
    public static function findRegionsByName(?string $query): array
    {
        return Queries::findRegionsByName(self::index(), $query);
    }

    /**
     * Finds provinces whose name contains the query. Case- and
     * accent-insensitive.
     *
     * @return list<Province>
     *
     * @example
     * Address::findProvincesByName('camarines'); // Camarines Norte, Camarines Sur
     */
    public static function findProvincesByName(?string $query): array
    {
        return Queries::findProvincesByName(self::index(), $query);
    }

    /**
     * Finds cities and municipalities whose name contains the query. Case- and
     * accent-insensitive, so 'paranaque' matches 'Parañaque'.
     *
     * @return list<City>
     *
     * @example
     * Address::findCitiesByName('paranaque'); // City of Parañaque
     */
    public static function findCitiesByName(?string $query): array
    {
        return Queries::findCitiesByName(self::index(), $query);
    }

    /**
     * Finds barangays whose name contains the query. Pass a city code to
     * search one city instead of all 42,010 barangays, which is much faster
     * and is what an address form usually wants.
     *
     * @return list<Barangay>
     *
     * @example
     * Address::findBarangaysByName('lahug', cityCode: '0730600000'); // Lahug
     */
    public static function findBarangaysByName(?string $query, ?string $cityCode = null): array
    {
        return Queries::findBarangaysByName(self::index(), $query, $cityCode);
    }

    /**
     * The PSGC reference date of the shipped dataset, as YYYY-MM-DD.
     *
     * @example
     * Address::psgcVersion(); // '2026-06-30'
     */
    public static function psgcVersion(): string
    {
        return self::index()->version();
    }

    /**
     * The index holds nothing but the dataset and the readonly objects built
     * from it, so keeping it between requests is safe under Octane and saves
     * reparsing the data files.
     */
    private static function index(): AddressIndex
    {
        return self::$index ??= new AddressIndex(new FileDataset(__DIR__.'/../data/psgc'));
    }
}
