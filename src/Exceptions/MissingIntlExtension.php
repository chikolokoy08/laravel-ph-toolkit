<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Exceptions;

use RuntimeException;

/**
 * Thrown when something needs the intl extension and it is not installed.
 *
 * This is a problem with the environment rather than with the value passed in,
 * which is why it throws where the formatters return null. Returning null here
 * would report a missing extension the same way as a malformed number, and the
 * fix is a server change, not a different input.
 */
final class MissingIntlExtension extends RuntimeException
{
    public static function forPesoFormatting(): self
    {
        return new self(
            'Currency::formatPeso() needs the intl extension, which is not installed. '.
            'Install it with your package manager, for example "apt install php-intl" on '.
            'Debian or Ubuntu, "brew install php" on macOS, or "docker-php-ext-install intl" '.
            'in a Docker image, then enable it by adding "extension=intl" to your php.ini and '.
            'restarting PHP. Check it with "php -m | grep intl". Everything else in this '.
            'package works without it.',
        );
    }
}
