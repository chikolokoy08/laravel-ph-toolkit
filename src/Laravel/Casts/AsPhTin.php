<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Laravel\Casts;

use Chikolokoy08\PhToolkit\Exceptions\InvalidPhValue;
use Chikolokoy08\PhToolkit\Tin;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a TIN in its formatted form, so "123456789000" and "123 456 789 000"
 * both end up as "123-456-789-000".
 *
 * Assigning something that is not a valid TIN throws rather than saving it.
 * Validate first with the PhTin rule if the value came from a user.
 *
 * @example
 * protected function casts(): array
 * {
 *     return ['tin' => AsPhTin::class];
 * }
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class AsPhTin implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $formatted = is_string($value) ? Tin::formatTin($value) : null;

        if ($formatted === null) {
            throw InvalidPhValue::tin($value);
        }

        return [$key => $formatted];
    }
}
