<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Psgc;

use JsonSerializable;

final readonly class City implements JsonSerializable
{
    /**
     * @param  string|null  $provinceCode  Null for cities that sit directly under a
     *                                     region, such as those in NCR.
     * @param  string|null  $parentCityCode  The city a sub-municipality belongs to,
     *                                       and null for everything else. The 14 sub-municipalities are
     *                                       the districts of the City of Manila, such as Tondo I/II and
     *                                       Intramuros.
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $provinceCode,
        public string $regionCode,
        public CityType $type,
        public ?string $parentCityCode,
    ) {
    }

    /**
     * @return array{code: string, name: string, provinceCode: string|null, regionCode: string, parentCityCode: string|null, type: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'provinceCode' => $this->provinceCode,
            'regionCode' => $this->regionCode,
            // parentCityCode before type, so the JSON matches key for key what
            // the ph-toolkit npm package emits for the same city.
            'parentCityCode' => $this->parentCityCode,
            'type' => $this->type->value,
        ];
    }

    /**
     * @return array{code: string, name: string, provinceCode: string|null, regionCode: string, parentCityCode: string|null, type: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
