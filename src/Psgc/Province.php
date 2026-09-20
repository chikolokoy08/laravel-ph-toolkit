<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Psgc;

use JsonSerializable;

final readonly class Province implements JsonSerializable
{
    /**
     * @param  bool  $isProvince  False for the two PSA entries that carry a
     *                            province code without being provinces: "City of Isabela (Not a
     *                            Province)" under Region IX, and "Special Geographic Area" under
     *                            BARMM. They are kept at the province level so the cities and
     *                            municipalities under them stay reachable, and their names are left
     *                            as the PSA publishes them.
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $regionCode,
        public bool $isProvince,
    ) {
    }

    /**
     * @return array{code: string, name: string, regionCode: string, isProvince: bool}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'regionCode' => $this->regionCode,
            'isProvince' => $this->isProvince,
        ];
    }

    /**
     * @return array{code: string, name: string, regionCode: string, isProvince: bool}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
