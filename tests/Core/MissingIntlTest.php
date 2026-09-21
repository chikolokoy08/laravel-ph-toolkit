<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Exceptions\MissingIntlExtension;

/**
 * The intl extension cannot be unloaded from a running process, and on many
 * builds it is compiled in, so `php -n` does not remove it either. These tests
 * run a fresh process that declares its own extension_loaded() inside the
 * package namespace, which shadows the global one for the unqualified call in
 * Currency. That is the same code path a server without intl takes.
 *
 * @return array{status: int, output: string}
 */
function withoutIntl(string $body): array
{
    $autoload = var_export(dirname(__DIR__, 2).'/vendor/autoload.php', true);

    // One shadow per namespace that calls it unqualified: PHP resolves an
    // unqualified function against the current namespace before the global one.
    $shadow = <<<'PHP'
        namespace Chikolokoy08\PhToolkit;

        function extension_loaded(string $name): bool
        {
            return $name === 'intl' ? false : \extension_loaded($name);
        }

        namespace Chikolokoy08\PhToolkit\Internal;

        function extension_loaded(string $name): bool
        {
            return $name === 'intl' ? false : \extension_loaded($name);
        }

        namespace Probe;
        PHP;

    $code = $shadow."\n\nrequire {$autoload};\n\n".$body;

    $process = proc_open(
        [PHP_BINARY, '-d', 'opcache.enable_cli=0', '-r', $code],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    if ($process === false) {
        throw new RuntimeException('Could not start a PHP process.');
    }

    $output = (string) stream_get_contents($pipes[1]).(string) stream_get_contents($pipes[2]);
    array_map(fclose(...), $pipes);

    return ['status' => proc_close($process), 'output' => $output];
}

it('shadows extension_loaded in the child process', function (): void {
    $result = withoutIntl('echo \Chikolokoy08\PhToolkit\Internal\Accents::supportsNormalizer() ? "yes" : "no";');

    expect($result['output'])->toBe('no');
});

it('throws MissingIntlExtension from formatPeso', function (): void {
    $result = withoutIntl(<<<'PHP'
        try {
            \Chikolokoy08\PhToolkit\Currency::formatPeso(1234.5);
            echo 'no exception';
        } catch (\Chikolokoy08\PhToolkit\Exceptions\MissingIntlExtension $e) {
            echo 'threw: ', $e->getMessage();
        }
        PHP);

    expect($result['output'])->toStartWith('threw: ')
        ->and($result['output'])->toContain('needs the intl extension')
        ->and($result['output'])->toContain('extension=intl')
        ->and($result['output'])->toContain('php -m | grep intl')
        ->and($result['output'])->toContain('Everything else in this package works without it');
});

it('throws before looking at the arguments', function (): void {
    // A missing extension is not something a different argument can fix, so it
    // is reported even for input that would otherwise return null.
    $result = withoutIntl(<<<'PHP'
        foreach ([[NAN, 'en-PH', 2], [1234.5, 'not a locale', 2], [1234.5, 'en-PH', -1]] as [$v, $l, $d]) {
            try {
                \Chikolokoy08\PhToolkit\Currency::formatPeso($v, $l, $d);
                echo 'no exception;';
            } catch (\Chikolokoy08\PhToolkit\Exceptions\MissingIntlExtension) {
                echo 'threw;';
            }
        }
        PHP);

    expect($result['output'])->toBe('threw;threw;threw;');
});

it('leaves everything else working without intl', function (): void {
    $result = withoutIntl(<<<'PHP'
        use Chikolokoy08\PhToolkit\Address;
        use Chikolokoy08\PhToolkit\Mobile;
        use Chikolokoy08\PhToolkit\Tin;
        use Chikolokoy08\PhToolkit\Zip;

        echo json_encode([
            'mobile' => Mobile::formatMobileNumber('(0917) 123-4567'),
            'mobileValid' => Mobile::isValidMobileNumber('0917 123 4567'),
            'tin' => Tin::formatTin('123456789000'),
            'tinValid' => Tin::isValidTin('123-456-789'),
            'zip' => Zip::isValidZipCode('6000'),
            'regions' => count(Address::getRegions()),
            'cebuCities' => count(Address::getCitiesByProvince('0702200000')),
            'cebuBarangays' => count(Address::getBarangaysByCity('0730600000')),
            'manilaBarangays' => count(Address::getBarangaysByCity('1380600000')),
            'version' => Address::psgcVersion(),
            'accentSearch' => array_map(fn ($c) => $c->name, Address::findCitiesByName('paranaque')),
            'plainSearch' => count(Address::findProvincesByName('camarines')),
        ]);
        PHP);

    expect(json_decode($result['output'], true))->toBe([
        'mobile' => '+639171234567',
        'mobileValid' => true,
        'tin' => '123-456-789-000',
        'tinValid' => true,
        'zip' => true,
        'regions' => 18,
        'cebuCities' => 50,
        'cebuBarangays' => 80,
        'manilaBarangays' => 897,
        'version' => '2026-06-30',
        'accentSearch' => ['City of Parañaque'],
        'plainSearch' => 2,
    ]);
});

it('is a runtime problem, not an argument problem', function (): void {
    expect(MissingIntlExtension::forPesoFormatting())->toBeInstanceOf(RuntimeException::class);
});
