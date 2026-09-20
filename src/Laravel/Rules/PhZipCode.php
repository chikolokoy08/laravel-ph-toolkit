<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Laravel\Rules;

use Chikolokoy08\PhToolkit\Zip;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Passes when the value is a valid Philippine ZIP code of exactly four digits.
 *
 * The message is translatable, and ships in English and Filipino.
 *
 * @example
 * $request->validate(['zip' => ['required', new PhZipCode]]); // '6000'
 */
final class PhZipCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && Zip::isValidZipCode($value)) {
            return;
        }

        $fail('ph-toolkit::validation.zip')->translate();
    }
}
