<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a value that has to be stored in a normalized form cannot be
 * normalized. The casts raise this instead of writing something unusable to
 * the database.
 */
final class InvalidPhValue extends InvalidArgumentException
{
    public static function mobileNumber(mixed $value): self
    {
        return new self(
            'Cannot store '.self::describe($value).' as a mobile number: it is not a valid '.
            'Philippine mobile number. Validate the input with the PhMobileNumber rule, or '.
            'Mobile::isValidMobileNumber(), before assigning it.',
        );
    }

    public static function tin(mixed $value): self
    {
        return new self(
            'Cannot store '.self::describe($value).' as a TIN: it is not a valid Philippine '.
            'TIN. Validate the input with the PhTin rule, or Tin::isValidTin(), before '.
            'assigning it.',
        );
    }

    /**
     * Renders a value short enough to read and safe to put in a message. The
     * input can be arbitrary bytes, so a string is only quoted when it is
     * valid UTF-8 and short.
     */
    private static function describe(mixed $value): string
    {
        if (! is_string($value)) {
            return 'a value of type '.get_debug_type($value);
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            return 'a string that is not valid UTF-8';
        }

        if (mb_strlen($value) > 40) {
            return '"'.mb_substr($value, 0, 40).'..."';
        }

        return '"'.$value.'"';
    }
}
