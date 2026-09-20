<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Psgc;

use JsonSerializable;

final readonly class Barangay implements JsonSerializable
{
    public function __construct(
        public string $code,
        public string $name,
        public string $cityCode,
    ) {
    }

    /**
     * @return array{code: string, name: string, cityCode: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'cityCode' => $this->cityCode,
        ];
    }

    /**
     * @return array{code: string, name: string, cityCode: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
