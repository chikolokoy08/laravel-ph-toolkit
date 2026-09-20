<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Laravel\Rules;

use Chikolokoy08\PhToolkit\Mobile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Passes when the value is a valid Philippine mobile number. Accepts the 09 range and the known 08 blocks, in local, 63, and +63 form.
 *
 * The message is translatable, and ships in English and Filipino.
 *
 * @example
 * $request->validate(['mobile' => ['required', new PhMobileNumber]]); // '0917 123 4567'
 */
final class PhMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && Mobile::isValidMobileNumber($value)) {
            return;
        }

        $fail('ph-toolkit::validation.mobile')->translate();
    }
}
