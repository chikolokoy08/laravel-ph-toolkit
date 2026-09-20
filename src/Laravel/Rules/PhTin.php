<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Laravel\Rules;

use Chikolokoy08\PhToolkit\Tin;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Passes when the value is a valid Philippine TIN of 9, 12, or 14 digits.
 *
 * The message is translatable, and ships in English and Filipino.
 *
 * @example
 * $request->validate(['tin' => ['required', new PhTin]]); // '123-456-789'
 */
final class PhTin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && Tin::isValidTin($value)) {
            return;
        }

        $fail('ph-toolkit::validation.tin')->translate();
    }
}
