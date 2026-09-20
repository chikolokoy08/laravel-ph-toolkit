<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Psgc\AddressIndex;
use Chikolokoy08\PhToolkit\Psgc\FileDataset;
use Chikolokoy08\PhToolkit\Psgc\Queries;
use Chikolokoy08\PhToolkit\Tests\Support\RecordingDataset;

function recordingIndex(): array
{
    $dataset = new RecordingDataset(new FileDataset(dirname(__DIR__, 2).'/data/psgc'));

    return [new AddressIndex($dataset), $dataset];
}

it('reads only the level a lookup needs', function (string $method, array $arguments, array $expected): void {
    [$index, $dataset] = recordingIndex();

    Queries::$method($index, ...$arguments);

    expect($dataset->requested())->toBe($expected);
})->with([
    'listing regions' => ['getRegions', [], ['regions']],
    'a region by code' => ['getRegionByCode', ['0700000000'], ['regions']],
    'provinces of a region' => ['getProvincesByRegion', ['0700000000'], ['provinces']],
    'listing cities' => ['getCities', [], ['cities']],
    'a city by code' => ['getCityByCode', ['0730600000'], ['cities']],
    'searching city names' => ['findCitiesByName', ['cebu'], ['cities']],
]);

it('reads cities alongside barangays, for the Manila rollup', function (): void {
    [$index, $dataset] = recordingIndex();

    Queries::getBarangaysByCity($index, '0730600000');

    expect($dataset->requested())->toBe(['barangays', 'cities']);
});

it('reads every level only once however often it is asked for', function (): void {
    $dataset = new RecordingDataset(new FileDataset(dirname(__DIR__, 2).'/data/psgc'));
    $index = new AddressIndex($dataset);

    for ($i = 0; $i < 5; $i++) {
        Queries::getRegions($index);
        Queries::getCities($index);
        Queries::getCitiesByRegion($index, '1300000000');
    }

    // requested() drops duplicates, so compare against the raw call count.
    $reflection = new ReflectionProperty(RecordingDataset::class, 'requested');

    /** @var list<string> $calls */
    $calls = $reflection->getValue($dataset);

    expect(array_count_values($calls))->toBe(['regions' => 1, 'cities' => 1]);
});

it('never touches a data file it does not need', function (): void {
    // A directory holding only regions.php: anything that reached for another
    // level would fail on the missing file rather than pass quietly.
    $directory = sys_get_temp_dir().'/ph-toolkit-lazy-'.getmypid();

    if (! is_dir($directory)) {
        mkdir($directory, 0o777, true);
    }

    copy(dirname(__DIR__, 2).'/data/psgc/regions.php', $directory.'/regions.php');

    try {
        $index = new AddressIndex(new FileDataset($directory));

        expect(Queries::getRegions($index))->toHaveCount(18)
            ->and(Queries::getRegionByCode($index, '0700000000')?->name)
            ->toBe('Region VII (Central Visayas)');
    } finally {
        unlink($directory.'/regions.php');
        rmdir($directory);
    }
});

it('explains itself when a data file is missing', function (): void {
    $index = new AddressIndex(new FileDataset('/nonexistent/psgc'));

    expect(fn (): array => Queries::getRegions($index))
        ->toThrow(RuntimeException::class, 'Run bin/build-psgc.php');
});
