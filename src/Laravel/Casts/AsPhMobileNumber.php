<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Laravel\Casts;

use Chikolokoy08\PhToolkit\Exceptions\InvalidPhValue;
use Chikolokoy08\PhToolkit\Mobile;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a mobile number in E.164, so "0917 123 4567" and "+63 917 123 4567"
 * end up as the same row value and two records for one person match.
 *
 * Assigning something that is not a valid mobile number throws rather than
 * saving it, because a column cast to this type is meant to hold only numbers
 * that came back from the formatter. Validate first with the PhMobileNumber
 * rule if the value came from a user.
 *
 * @example
 * protected function casts(): array
 * {
 *     return ['mobile' => AsPhMobileNumber::class];
 * }
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class AsPhMobileNumber implements CastsAttributes
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

        $e164 = is_string($value) ? Mobile::formatMobileNumber($value) : null;

        if ($e164 === null) {
            throw InvalidPhValue::mobileNumber($value);
        }

        return [$key => $e164];
    }
}
