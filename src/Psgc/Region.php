<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Psgc;

use JsonSerializable;

final readonly class Region implements JsonSerializable
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }

    /**
     * @return array{code: string, name: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
        ];
    }

    /**
     * @return array{code: string, name: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
