<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Psgc;

use Chikolokoy08\PhToolkit\Internal\Accents;
use Chikolokoy08\PhToolkit\Internal\Separators;

/**
 * The lookups, written against any index so they can be tested on a small
 * fixture rather than the shipped dataset. Address binds them to the real one.
 *
 * @internal
 */
final class Queries
{
    private function __construct()
    {
        //
    }

    /** @return list<Region> */
    public static function getRegions(AddressIndex $index): array
    {
        return $index->regions();
    }

    public static function getRegionByCode(AddressIndex $index, ?string $code): ?Region
    {
        return $code === null ? null : ($index->regionByCode()[$code] ?? null);
    }

    /** @return list<Province> */
    public static function getProvinces(AddressIndex $index): array
    {
        return $index->provinces();
    }

    public static function getProvinceByCode(AddressIndex $index, ?string $code): ?Province
    {
        return $code === null ? null : ($index->provinceByCode()[$code] ?? null);
    }

    /** @return list<Province> */
    public static function getProvincesByRegion(AddressIndex $index, ?string $regionCode): array
    {
        return $regionCode === null ? [] : ($index->provincesByRegion()[$regionCode] ?? []);
    }

    /** @return list<City> */
    public static function getCities(AddressIndex $index, bool $includeSubMunicipalities = false): array
    {
        return self::cityList($index->cities(), $includeSubMunicipalities);
    }

    public static function getCityByCode(AddressIndex $index, ?string $code): ?City
    {
        return $code === null ? null : ($index->cityByCode()[$code] ?? null);
    }

    /** @return list<City> */
    public static function getCitiesByProvince(
        AddressIndex $index,
        ?string $provinceCode,
        bool $includeSubMunicipalities = false,
    ): array {
        $cities = $provinceCode === null ? [] : ($index->citiesByProvince()[$provinceCode] ?? []);

        return self::cityList($cities, $includeSubMunicipalities);
    }

    /** @return list<City> */
    public static function getCitiesByRegion(
        AddressIndex $index,
        ?string $regionCode,
        bool $includeSubMunicipalities = false,
    ): array {
        $cities = $regionCode === null ? [] : ($index->citiesByRegion()[$regionCode] ?? []);

        return self::cityList($cities, $includeSubMunicipalities);
    }

    /** @return list<City> */
    public static function getSubMunicipalitiesByCity(AddressIndex $index, ?string $cityCode): array
    {
        return $cityCode === null ? [] : ($index->subMunicipalitiesByParent()[$cityCode] ?? []);
    }

    public static function getBarangayByCode(AddressIndex $index, ?string $code): ?Barangay
    {
        return $code === null ? null : ($index->barangayByCode()[$code] ?? null);
    }

    /** @return list<Barangay> */
    public static function getBarangaysByCity(AddressIndex $index, ?string $cityCode): array
    {
        return $cityCode === null ? [] : ($index->barangaysByCity()[$cityCode] ?? []);
    }

    /** @return list<Region> */
    public static function findRegionsByName(AddressIndex $index, ?string $query): array
    {
        return self::search($index->regions(), $query);
    }

    /** @return list<Province> */
    public static function findProvincesByName(AddressIndex $index, ?string $query): array
    {
        return self::search($index->provinces(), $query);
    }

    /** @return list<City> */
    public static function findCitiesByName(AddressIndex $index, ?string $query): array
    {
        return self::search($index->cities(), $query);
    }

    /** @return list<Barangay> */
    public static function findBarangaysByName(
        AddressIndex $index,
        ?string $query,
        ?string $cityCode = null,
    ): array {
        $scope = $cityCode === null
            ? $index->barangays()
            : ($index->barangaysByCity()[$cityCode] ?? []);

        return self::search($scope, $query);
    }

    /**
     * Sub-municipalities are the districts of one city, not places of their
     * own, so a city list leaves them out unless they are asked for.
     *
     * @param  list<City>  $cities
     * @return list<City>
     */
    private static function cityList(array $cities, bool $includeSubMunicipalities): array
    {
        if ($includeSubMunicipalities) {
            return $cities;
        }

        return array_values(array_filter(
            $cities,
            static fn (City $city): bool => $city->type !== CityType::SubMunicipality,
        ));
    }

    /**
     * @template T of Region|Province|City|Barangay
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private static function search(array $items, ?string $query): array
    {
        $needle = self::toNeedle($query);

        if ($needle === null) {
            return [];
        }

        return array_values(array_filter(
            $items,
            static fn (Region|Province|City|Barangay $item): bool => str_contains(Accents::fold($item->name), $needle),
        ));
    }

    private static function toNeedle(?string $query): ?string
    {
        if ($query === null) {
            return null;
        }

        $trimmed = Separators::trim($query);

        return $trimmed === null || $trimmed === '' ? null : Accents::fold($trimmed);
    }

}
