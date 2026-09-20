<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Psgc;

/**
 * Turns the raw tuples into objects and the lookup maps built from them.
 *
 * Every structure is built on first use and kept, so a long-lived process such
 * as an Octane worker pays for a level once. Nothing here depends on a request
 * or on configuration, and the objects are readonly, so the cache is safe to
 * share between requests.
 */
final class AddressIndex
{
    /** @var list<Region>|null */
    private ?array $regions = null;

    /** @var array<string, Region>|null */
    private ?array $regionByCode = null;

    /** @var list<Province>|null */
    private ?array $provinces = null;

    /** @var array<string, Province>|null */
    private ?array $provinceByCode = null;

    /** @var array<string, list<Province>>|null */
    private ?array $provincesByRegion = null;

    /** @var list<City>|null */
    private ?array $cities = null;

    /** @var array<string, City>|null */
    private ?array $cityByCode = null;

    /** @var array<string, list<City>>|null */
    private ?array $citiesByProvince = null;

    /** @var array<string, list<City>>|null */
    private ?array $citiesByRegion = null;

    /** @var array<string, list<City>>|null */
    private ?array $subMunicipalitiesByParent = null;

    /** @var list<Barangay>|null */
    private ?array $barangays = null;

    /** @var array<string, Barangay>|null */
    private ?array $barangayByCode = null;

    /** @var array<string, list<Barangay>>|null */
    private ?array $barangaysByCity = null;

    public function __construct(private readonly Dataset $dataset)
    {
    }

    /** @return list<Region> */
    public function regions(): array
    {
        return $this->regions ??= array_map(
            static fn (array $row): Region => new Region($row[0], $row[1]),
            $this->dataset->regions(),
        );
    }

    /** @return array<string, Region> */
    public function regionByCode(): array
    {
        return $this->regionByCode ??= self::byCode(
            $this->regions(),
            static fn (Region $region): string => $region->code,
        );
    }

    /** @return list<Province> */
    public function provinces(): array
    {
        return $this->provinces ??= array_map(
            static fn (array $row): Province => new Province($row[0], $row[1], $row[2], $row[3]),
            $this->dataset->provinces(),
        );
    }

    /** @return array<string, Province> */
    public function provinceByCode(): array
    {
        return $this->provinceByCode ??= self::byCode(
            $this->provinces(),
            static fn (Province $province): string => $province->code,
        );
    }

    /** @return array<string, list<Province>> */
    public function provincesByRegion(): array
    {
        return $this->provincesByRegion ??= self::groupBy(
            $this->provinces(),
            static fn (Province $province): string => $province->regionCode,
        );
    }

    /** @return list<City> */
    public function cities(): array
    {
        return $this->cities ??= array_map(
            static fn (array $row): City => new City(
                $row[0],
                $row[1],
                $row[2],
                $row[3],
                CityType::from($row[4]),
                $row[5],
            ),
            $this->dataset->cities(),
        );
    }

    /** @return array<string, City> */
    public function cityByCode(): array
    {
        return $this->cityByCode ??= self::byCode(
            $this->cities(),
            static fn (City $city): string => $city->code,
        );
    }

    /** @return array<string, list<City>> */
    public function citiesByProvince(): array
    {
        return $this->citiesByProvince ??= self::groupBy(
            $this->cities(),
            static fn (City $city): ?string => $city->provinceCode,
        );
    }

    /** @return array<string, list<City>> */
    public function citiesByRegion(): array
    {
        return $this->citiesByRegion ??= self::groupBy(
            $this->cities(),
            static fn (City $city): string => $city->regionCode,
        );
    }

    /** @return array<string, list<City>> */
    public function subMunicipalitiesByParent(): array
    {
        return $this->subMunicipalitiesByParent ??= self::groupBy(
            $this->cities(),
            static fn (City $city): ?string => $city->parentCityCode,
        );
    }

    /** @return list<Barangay> */
    public function barangays(): array
    {
        return $this->barangays ??= array_map(
            static fn (array $row): Barangay => new Barangay($row[0], $row[1], $row[2]),
            $this->dataset->barangays(),
        );
    }

    /** @return array<string, Barangay> */
    public function barangayByCode(): array
    {
        return $this->barangayByCode ??= self::byCode(
            $this->barangays(),
            static fn (Barangay $barangay): string => $barangay->code,
        );
    }

    /** @return array<string, list<Barangay>> */
    public function barangaysByCity(): array
    {
        return $this->barangaysByCity ??= $this->buildBarangaysByCity();
    }

    public function version(): string
    {
        return $this->dataset->version();
    }

    /** @return array<string, list<Barangay>> */
    private function buildBarangaysByCity(): array
    {
        $groups = self::groupBy(
            $this->barangays(),
            static fn (Barangay $barangay): string => $barangay->cityCode,
        );

        // The City of Manila holds no barangays of its own; they belong to its
        // sub-municipalities. Rolling them up means a region to city to
        // barangay dropdown works there without a special case, while a
        // district still answers for its own barangays.
        foreach ($this->cities() as $city) {
            if ($city->parentCityCode === null) {
                continue;
            }

            $own = $groups[$city->code] ?? [];
            $parent = $groups[$city->parentCityCode] ?? null;

            $groups[$city->parentCityCode] = $parent === null ? $own : [...$parent, ...$own];
        }

        return $groups;
    }

    /**
     * @template T of object
     *
     * @param  list<T>  $items
     * @param  callable(T): string  $codeOf
     * @return array<string, T>
     */
    private static function byCode(array $items, callable $codeOf): array
    {
        $map = [];

        foreach ($items as $item) {
            $map[$codeOf($item)] = $item;
        }

        return $map;
    }

    /**
     * @template T of object
     *
     * @param  list<T>  $items
     * @param  callable(T): (string|null)  $parentOf
     * @return array<string, list<T>>
     */
    private static function groupBy(array $items, callable $parentOf): array
    {
        $groups = [];

        foreach ($items as $item) {
            $parent = $parentOf($item);

            if ($parent === null) {
                continue;
            }

            $groups[$parent][] = $item;
        }

        return $groups;
    }
}
