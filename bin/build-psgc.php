<?php

declare(strict_types=1);

/**
 * Converts the PSA PSGC publication workbook in data/raw/ into the compact PHP
 * arrays that the address lookups ship.
 *
 * The publication file lists one row per geographic unit, ordered so that a
 * region is followed by its provinces, each province by its cities and
 * municipalities, and each of those by its barangays. This script derives the
 * hierarchy from that order and the Geographic Level column rather than from
 * the digits of the code, so it does not depend on the code layout. If the
 * order assumption does not hold, or a level is not recognised, the script
 * stops with the offending row number instead of writing partial data.
 */

namespace Chikolokoy08\PhToolkit\Build;

use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use RuntimeException;
use Throwable;

require_once __DIR__.'/../vendor/autoload.php';

/**
 * Fill this in before running the script: the publication date printed on the
 * PSA datafile in data/raw/, as YYYY-MM-DD. It becomes the value returned by
 * Address::psgcVersion(). The script checks it against the workbook's own
 * Metadata sheet and stops if the two disagree.
 */
const PSGC_RELEASE_DATE = '2026-06-30';

const SOURCE = 'Philippine Statistics Authority, Philippine Standard Geographic Code (PSGC)';

const ROOT = __DIR__.'/..';
const RAW_DIR = ROOT.'/data/raw';
const OUT_DIR = ROOT.'/data/psgc';

/**
 * PhpSpreadsheet holds every cell it reads as an object, so the whole sheet at
 * once costs more memory than PHP's default limit allows. Reading in row
 * chunks and discarding each one keeps the peak around 66 MB, at the cost of
 * reparsing the file once per chunk.
 */
const CHUNK_SIZE = 10000;

/** How far down a sheet to look for the header row. */
const HEADER_SEARCH_ROWS = 25;

const LEVELS = [
    'reg' => 'region',
    'region' => 'region',
    'prov' => 'province',
    'province' => 'province',
    'city' => 'city',
    'mun' => 'municipality',
    'municipality' => 'municipality',
    'submun' => 'submunicipality',
    'submunicipality' => 'submunicipality',
    'bgy' => 'barangay',
    'brgy' => 'barangay',
    'barangay' => 'barangay',
];

const CITY_TYPES = [
    'city' => 'City',
    'municipality' => 'Municipality',
    'submunicipality' => 'SubMunicipality',
];

final class BuildError extends RuntimeException
{
}

/**
 * Reads one rectangle of a sheet, so a large file can be walked in chunks
 * without holding all of it.
 */
final class Rectangle implements IReadFilter
{
    /** @param  list<string>  $columns */
    public function __construct(
        private readonly array $columns,
        private readonly int $fromRow,
        private readonly int $toRow,
    ) {
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row >= $this->fromRow
            && $row <= $this->toRow
            && in_array($columnAddress, $this->columns, true);
    }
}

function fail(string $message): never
{
    throw new BuildError($message);
}

function normalizeKey(string $value): string
{
    return (string) preg_replace('/[^a-z0-9]/', '', strtolower($value));
}

function cellText(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    if ($value instanceof RichText) {
        return trim($value->getPlainText());
    }

    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    // Excel hands back whole numbers as floats. Casting straight to string
    // would render large codes in exponential notation.
    if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
        return (string) (int) $value;
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return is_string($value) ? trim($value) : '';
}

function findWorkbook(): string
{
    if (! is_dir(RAW_DIR)) {
        fail('Could not read '.RAW_DIR.'. Create it and put the PSA PSGC workbook there.');
    }

    $workbooks = array_values(array_filter(
        scandir(RAW_DIR) ?: [],
        static fn (string $name): bool => str_ends_with($name, '.xlsx') && ! str_starts_with($name, '~$'),
    ));

    if ($workbooks === []) {
        fail('No .xlsx file in '.RAW_DIR.'. Download the PSGC publication file from the PSA and put it there.');
    }

    if (count($workbooks) > 1) {
        fail('Found more than one .xlsx in '.RAW_DIR.': '.implode(', ', $workbooks).'. Leave only one.');
    }

    return RAW_DIR.'/'.$workbooks[0];
}

/**
 * The Metadata sheet carries the publication date as "30 June 2026". Other
 * sheets carry an "As of" heading that is not always updated, so only this one
 * is trusted.
 */
function readPublicationDate(string $file): ?string
{
    $reader = IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);

    if (! in_array('Metadata', $reader->listWorksheetNames($file), true)) {
        return null;
    }

    $reader->setLoadSheetsOnly(['Metadata']);
    $reader->setReadFilter(new Rectangle(['A', 'B'], 1, 200));
    $sheet = $reader->load($file)->getActiveSheet();

    foreach ($sheet->getRowIterator() as $row) {
        $number = $row->getRowIndex();

        if (normalizeKey(cellText($sheet->getCell('A'.$number)->getValue())) !== 'publicationdate') {
            continue;
        }

        $raw = cellText($sheet->getCell('B'.$number)->getValue());

        if (preg_match('/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/', $raw, $match) !== 1) {
            continue;
        }

        $month = array_search(strtolower($match[2]), [
            1 => 'january', 'february', 'march', 'april', 'may', 'june',
            'july', 'august', 'september', 'october', 'november', 'december',
        ], true);

        if ($month === false) {
            continue;
        }

        return sprintf('%s-%02d-%02d', $match[3], $month, (int) $match[1]);
    }

    return null;
}

/**
 * @return array{sheet: string, row: int, code: string, name: string, level: string}|null
 */
function findHeader(string $file): ?array
{
    $reader = IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);

    foreach ($reader->listWorksheetNames($file) as $name) {
        $scan = IOFactory::createReaderForFile($file);
        $scan->setReadDataOnly(true);
        $scan->setLoadSheetsOnly([$name]);
        $scan->setReadFilter(new Rectangle(
            range('A', 'Z'),
            1,
            HEADER_SEARCH_ROWS,
        ));

        $sheet = $scan->load($file)->getActiveSheet();

        foreach ($sheet->getRowIterator() as $row) {
            $code = $level = $nameColumn = null;

            $cells = $row->getCellIterator();
            $cells->setIterateOnlyExistingCells(true);

            foreach ($cells as $cell) {
                $key = normalizeKey(cellText($cell->getValue()));
                $column = $cell->getColumn();

                if ($code === null && str_contains($key, 'psgc') && ! str_contains($key, 'correspondence')) {
                    $code = $column;
                }
                if ($nameColumn === null && $key === 'name') {
                    $nameColumn = $column;
                }
                if ($level === null && str_contains($key, 'geographiclevel')) {
                    $level = $column;
                }
            }

            if ($code !== null && $nameColumn !== null && $level !== null) {
                return [
                    'sheet' => $name,
                    'row' => $row->getRowIndex(),
                    'code' => $code,
                    'name' => $nameColumn,
                    'level' => $level,
                ];
            }
        }
    }

    return null;
}

function describeSheets(string $file): string
{
    $reader = IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $lines = [];

    foreach ($reader->listWorksheetNames($file) as $name) {
        $scan = IOFactory::createReaderForFile($file);
        $scan->setReadDataOnly(true);
        $scan->setLoadSheetsOnly([$name]);
        $scan->setReadFilter(new Rectangle(range('A', 'Z'), 1, 10));
        $sheet = $scan->load($file)->getActiveSheet();

        $lines[] = $name;

        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            $iterator = $row->getCellIterator();
            $iterator->setIterateOnlyExistingCells(true);

            foreach ($iterator as $cell) {
                $text = cellText($cell->getValue());
                if ($text !== '') {
                    $cells[] = $text;
                }
            }

            if ($cells !== []) {
                $lines[] = '  row '.$row->getRowIndex().': '.implode(' | ', $cells);
            }
        }
    }

    return implode("\n", $lines);
}

/**
 * @param  array{sheet: string, row: int, code: string, name: string, level: string}  $header
 * @return list<array{rowNumber: int, code: string, name: string, level: string|null}>
 */
function readRows(string $file, array $header): array
{
    $reader = IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $reader->setLoadSheetsOnly([$header['sheet']]);

    $columns = [$header['code'], $header['name'], $header['level']];
    $lastRow = lastRow($file, $header['sheet']);
    $rows = [];

    for ($start = $header['row'] + 1; $start <= $lastRow; $start += CHUNK_SIZE) {
        $reader->setReadFilter(new Rectangle($columns, $start, $start + CHUNK_SIZE - 1));
        $book = $reader->load($file);
        $sheet = $book->getActiveSheet();

        foreach ($sheet->getRowIterator($start) as $row) {
            $number = $row->getRowIndex();

            $code = cellText($sheet->getCell($header['code'].$number)->getValue());
            $name = cellText($sheet->getCell($header['name'].$number)->getValue());
            $levelText = cellText($sheet->getCell($header['level'].$number)->getValue());

            if ($code === '' && $name === '' && $levelText === '') {
                continue;
            }

            if ($code === '' || $name === '') {
                fail("Row {$number} is missing a code or a name: code \"{$code}\", name \"{$name}\".");
            }

            if ($levelText === '') {
                // The PSA leaves this blank for entries that hold a province
                // code without being provinces.
                $rows[] = ['rowNumber' => $number, 'code' => $code, 'name' => $name, 'level' => null];

                continue;
            }

            $level = LEVELS[normalizeKey($levelText)] ?? null;

            if ($level === null) {
                fail(
                    "Row {$number} has an unrecognised geographic level \"{$levelText}\". ".
                    'Add it to LEVELS in bin/build-psgc.php once you know which level it belongs to.',
                );
            }

            $rows[] = ['rowNumber' => $number, 'code' => $code, 'name' => $name, 'level' => $level];
        }

        $book->disconnectWorksheets();
        unset($book);
    }

    return $rows;
}

function lastRow(string $file, string $sheetName): int
{
    $reader = IOFactory::createReaderForFile($file);

    foreach ($reader->listWorksheetInfo($file) as $info) {
        if ($info['worksheetName'] === $sheetName) {
            return (int) $info['totalRows'];
        }
    }

    fail("Sheet \"{$sheetName}\" disappeared between passes.");
}

function trailingZeros(string $code): int
{
    return strlen($code) - strlen(rtrim($code, '0'));
}

/**
 * Every level of the PSGC code is a fixed-width segment, so a row that belongs
 * at a given level has that many trailing zeros: a province code such as
 * 0702200000 (Cebu) has five. The widths are measured from the rows whose
 * level the file states, rather than assumed, so a change to the coding
 * structure shows up as a validation failure instead of wrong parents.
 *
 * @param  list<array{rowNumber: int, code: string, name: string, level: string|null}>  $rows
 * @return array{province: int, city: int}
 */
function calibrate(array $rows): array
{
    $zerosFor = static function (array $levels) use ($rows): array {
        $zeros = [];
        foreach ($rows as $row) {
            if ($row['level'] !== null && in_array($row['level'], $levels, true)) {
                $zeros[] = trailingZeros($row['code']);
            }
        }

        return $zeros;
    };

    $province = $zerosFor(['province']);
    $city = $zerosFor(['city', 'municipality', 'submunicipality']);

    if ($province === [] || $city === []) {
        fail('The file has no province rows or no city rows, so the code structure cannot be read.');
    }

    return ['province' => min($province), 'city' => min($city)];
}

/**
 * Excel drops leading zeros from numeric cells, so codes are padded back to a
 * fixed width.
 *
 * @param  list<array{rowNumber: int, code: string, name: string, level: string|null}>  $rows
 * @return list<array{rowNumber: int, code: string, name: string, level: string|null}>
 */
function padCodes(array $rows): array
{
    $width = 0;
    foreach ($rows as $row) {
        $width = max($width, strlen($row['code']));
    }

    return array_map(static function (array $row) use ($width): array {
        if (preg_match('/^\d+$/', $row['code']) !== 1) {
            fail("Row {$row['rowNumber']} has a non-numeric PSGC code \"{$row['code']}\".");
        }

        $row['code'] = str_pad($row['code'], $width, '0', STR_PAD_LEFT);

        return $row;
    }, $rows);
}

/**
 * A sub-municipality's code is its parent city's code with the city segment
 * filled in, so zeroing that segment gives the parent. The result is checked
 * against the city the file actually lists above it, and a disagreement stops
 * the build rather than guessing which one is right.
 *
 * @param  array{rowNumber: int, code: string, name: string, level: string|null}  $row
 * @param  array{province: int, city: int}  $slots
 */
function subMunicipalityParent(array $row, array $slots, ?string $listedAbove): string
{
    $derived = str_pad(
        substr($row['code'], 0, strlen($row['code']) - $slots['province']),
        strlen($row['code']),
        '0',
    );

    if ($derived !== $listedAbove) {
        fail(
            "Row {$row['rowNumber']}: sub-municipality \"{$row['name']}\" has a code under ".
            "{$derived}, but the city listed above it is ".($listedAbove ?? 'none').'.',
        );
    }

    return $derived;
}

/**
 * @param  list<array{rowNumber: int, code: string, name: string, level: string|null}>  $rows
 * @param  array{province: int, city: int}  $slots
 * @return array{dataset: array{regions: list<array{0: string, 1: string}>, provinces: list<array{0: string, 1: string, 2: string, 3: bool}>, cities: list<array{0: string, 1: string, 2: string|null, 3: string, 4: string, 5: string|null}>, barangays: list<array{0: string, 1: string, 2: string}>}, flagged: list<array{rowNumber: int, code: string, name: string, level: string|null}>}
 */
function buildDataset(array $rows, array $slots): array
{
    $dataset = ['regions' => [], 'provinces' => [], 'cities' => [], 'barangays' => []];
    $flagged = [];

    $region = null;
    $province = null;
    $city = null;
    $parentCity = null;

    foreach ($rows as $row) {
        // The PSA leaves the level blank for entries that hold a province code
        // without being provinces, such as "City of Isabela (Not a Province)"
        // and "Special Geographic Area". They are kept so the cities beneath
        // them stay reachable through their region, and marked isProvince
        // false.
        if ($row['level'] === null) {
            if ($region === null) {
                fail("Row {$row['rowNumber']}: \"{$row['name']}\" has no level and no region above it.");
            }

            $flagged[] = $row;
            $province = $row['code'];
            $city = null;
            $dataset['provinces'][] = [$row['code'], $row['name'], $region, false];

            continue;
        }

        switch ($row['level']) {
            case 'region':
                $region = $row['code'];
                $province = null;
                $city = null;
                $dataset['regions'][] = [$row['code'], $row['name']];
                break;

            case 'province':
                if ($region === null) {
                    fail("Row {$row['rowNumber']}: province \"{$row['name']}\" appears before any region.");
                }
                $province = $row['code'];
                $city = null;
                $dataset['provinces'][] = [$row['code'], $row['name'], $region, true];
                break;

            case 'city':
            case 'municipality':
            case 'submunicipality':
                if ($region === null) {
                    fail("Row {$row['rowNumber']}: \"{$row['name']}\" appears before any region.");
                }

                // Independent cities such as City of Zamboanga, City of
                // Baguio, and the cities of NCR hold a province-level code and
                // sit directly under their region, so they must not inherit
                // the province listed above.
                if (trailingZeros($row['code']) >= $slots['province']) {
                    $province = null;
                }

                $dataset['cities'][] = [
                    $row['code'],
                    $row['name'],
                    $province,
                    $region,
                    CITY_TYPES[$row['level']],
                    $row['level'] === 'submunicipality'
                        ? subMunicipalityParent($row, $slots, $parentCity)
                        : null,
                ];

                if ($row['level'] !== 'submunicipality') {
                    $parentCity = $row['code'];
                }

                $city = $row['code'];
                break;

            case 'barangay':
                if ($city === null) {
                    fail("Row {$row['rowNumber']}: barangay \"{$row['name']}\" appears before any city or municipality.");
                }
                $dataset['barangays'][] = [$row['code'], $row['name'], $city];
                break;
        }
    }

    return ['dataset' => $dataset, 'flagged' => $flagged];
}

/**
 * @param  array{regions: list<array{0: string, 1: string}>, provinces: list<array{0: string, 1: string, 2: string, 3: bool}>, cities: list<array{0: string, 1: string, 2: string|null, 3: string, 4: string, 5: string|null}>, barangays: list<array{0: string, 1: string, 2: string}>}  $dataset
 * @param  array{province: int, city: int}  $slots
 */
function validate(array $dataset, array $slots): void
{
    $seen = [];

    foreach (['regions', 'provinces', 'cities', 'barangays'] as $level) {
        foreach ($dataset[$level] as $entry) {
            if (isset($seen[$entry[0]])) {
                fail("Duplicate PSGC code {$entry[0]}.");
            }
            $seen[$entry[0]] = true;
        }
    }

    $regions = array_flip(array_column($dataset['regions'], 0));
    $provinces = array_flip(array_column($dataset['provinces'], 0));
    $cities = array_flip(array_column($dataset['cities'], 0));
    $parents = array_flip(array_filter(array_column($dataset['cities'], 2), is_string(...)));

    foreach ($dataset['provinces'] as [$code, $name, $regionCode]) {
        if (! isset($regions[$regionCode])) {
            fail("Province {$code} \"{$name}\" points at unknown region {$regionCode}.");
        }
    }

    foreach ($dataset['cities'] as [$code, $name, $provinceCode, $regionCode]) {
        if ($provinceCode !== null && ! isset($provinces[$provinceCode])) {
            fail("City {$code} \"{$name}\" points at unknown province {$provinceCode}.");
        }
        if (! isset($regions[$regionCode])) {
            fail("City {$code} \"{$name}\" points at unknown region {$regionCode}.");
        }
    }

    foreach ($dataset['barangays'] as [$code, $name, $cityCode]) {
        if (! isset($cities[$cityCode])) {
            fail("Barangay {$code} \"{$name}\" points at unknown city {$cityCode}.");
        }
    }

    // A child's code carries its parent's code as a prefix. Checking that
    // catches a row attached to the wrong parent, which row order alone would
    // not.
    $provincePrefix = static fn (string $code): string => substr($code, 0, strlen($code) - $slots['province']);
    $cityPrefix = static fn (string $code): string => substr($code, 0, strlen($code) - $slots['city']);

    foreach ($dataset['cities'] as [$code, $name, $provinceCode]) {
        if ($provinceCode !== null && $provincePrefix($code) !== $provincePrefix($provinceCode)) {
            fail("City {$code} \"{$name}\" is attached to province {$provinceCode}, but their codes do not share a prefix.");
        }
    }

    foreach ($dataset['barangays'] as [$code, $name, $cityCode]) {
        if ($cityPrefix($code) !== $cityPrefix($cityCode)) {
            fail("Barangay {$code} \"{$name}\" is attached to city {$cityCode}, but their codes do not share a prefix.");
        }
    }

    $cityType = array_combine(array_column($dataset['cities'], 0), array_column($dataset['cities'], 4));

    foreach ($dataset['cities'] as [$code, $name, , , , $parentCityCode]) {
        if ($parentCityCode === null) {
            continue;
        }
        if (! isset($cityType[$parentCityCode])) {
            fail("Sub-municipality {$code} \"{$name}\" points at unknown city {$parentCityCode}.");
        }
        if ($cityType[$parentCityCode] === 'SubMunicipality') {
            fail("Sub-municipality {$code} \"{$name}\" points at another sub-municipality.");
        }
    }

    // A flagged row that turned out not to be province-level would sit here
    // with nothing under it, which is worth stopping for.
    foreach ($dataset['provinces'] as [$code, $name, , $isProvince]) {
        if (! $isProvince && ! isset($parents[$code])) {
            fail(
                "{$code} \"{$name}\" has a blank geographic level and no cities or municipalities under it. ".
                'Check what the PSA means by that row before shipping it as a province.',
            );
        }
    }
}

function phpString(string $value): string
{
    return "'".strtr($value, ['\\' => '\\\\', "'" => "\\'"])."'";
}

function phpValue(string|bool|null $value): string
{
    return match (true) {
        $value === null => 'null',
        $value === true => 'true',
        $value === false => 'false',
        default => phpString($value),
    };
}

/**
 * @param  list<array<int, string|bool|null>>  $entries
 */
function writeTuples(string $path, string $description, string $sourceFile, array $entries): void
{
    $lines = array_map(
        static fn (array $entry): string => '    ['.implode(', ', array_map(phpValue(...), $entry)).'],',
        $entries,
    );

    $body = $lines === [] ? "return [];\n" : "return [\n".implode("\n", $lines)."\n];\n";

    write($path, $description, $sourceFile, $body);
}

function write(string $path, string $description, string $sourceFile, string $body): void
{
    $contents = <<<PHP
        <?php

        /**
         * {$description}
         *
         * Generated by bin/build-psgc.php from {$sourceFile}. Do not edit by hand.
         * See docs/updating-psgc.md to rebuild it from a new PSA release.
         */

        declare(strict_types=1);

        {$body}
        PHP;

    if (file_put_contents($path, $contents) === false) {
        fail("Could not write {$path}.");
    }
}

function main(): void
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', PSGC_RELEASE_DATE) !== 1) {
        fail(
            'PSGC_RELEASE_DATE is not set. Open bin/build-psgc.php and set it to the publication '.
            'date of the PSA datafile in data/raw/, as YYYY-MM-DD.',
        );
    }

    $file = findWorkbook();
    $name = basename($file);

    $published = readPublicationDate($file);

    if ($published !== null && $published !== PSGC_RELEASE_DATE) {
        fail(
            'PSGC_RELEASE_DATE is '.PSGC_RELEASE_DATE." but {$name} was published on ".
            "{$published}. Update the constant in bin/build-psgc.php.",
        );
    }

    $header = findHeader($file);

    if ($header === null) {
        fail(
            "No sheet in {$name} has a header row with a PSGC code, a name, and a geographic ".
            "level column. Sheets and their first rows:\n\n".describeSheets($file),
        );
    }

    $rows = padCodes(readRows($file, $header));

    if ($rows === []) {
        fail("No data rows below the header in sheet \"{$header['sheet']}\".");
    }

    $slots = calibrate($rows);
    ['dataset' => $dataset, 'flagged' => $flagged] = buildDataset($rows, $slots);
    validate($dataset, $slots);

    if (! is_dir(OUT_DIR) && ! mkdir(OUT_DIR, 0o755, true) && ! is_dir(OUT_DIR)) {
        fail('Could not create '.OUT_DIR.'.');
    }

    writeTuples(OUT_DIR.'/regions.php', 'PSGC regions, as [code, name].', $name, $dataset['regions']);
    writeTuples(OUT_DIR.'/provinces.php', 'PSGC provinces, as [code, name, regionCode, isProvince].', $name, $dataset['provinces']);
    writeTuples(OUT_DIR.'/cities.php', 'PSGC cities and municipalities, as [code, name, provinceCode, regionCode, type, parentCityCode].', $name, $dataset['cities']);
    writeTuples(OUT_DIR.'/barangays.php', 'PSGC barangays, as [code, name, cityCode].', $name, $dataset['barangays']);

    $counts = [
        'regions' => count($dataset['regions']),
        'provinces' => count($dataset['provinces']),
        'cities' => count($dataset['cities']),
        'barangays' => count($dataset['barangays']),
    ];

    write(
        OUT_DIR.'/meta.php',
        'Release details for the PSGC dataset in this directory.',
        $name,
        "return [\n".
        '    '.phpString('release').' => '.phpString(PSGC_RELEASE_DATE).",\n".
        '    '.phpString('source').' => '.phpString(SOURCE).",\n".
        '    '.phpString('sourceFile').' => '.phpString($name).",\n".
        '    '.phpString('counts')." => [\n".
        implode('', array_map(
            static fn (string $key, int $value): string => '        '.phpString($key)." => {$value},\n",
            array_keys($counts),
            array_values($counts),
        )).
        "    ],\n];\n",
    );

    echo "Read {$name}, sheet \"{$header['sheet']}\", ".count($rows)." rows.\n";
    echo "Wrote {$counts['regions']} regions, {$counts['provinces']} provinces, ".
        "{$counts['cities']} cities and municipalities, {$counts['barangays']} barangays.\n";

    $subMunicipalities = count(array_filter(
        $dataset['cities'],
        static fn (array $entry): bool => $entry[5] !== null,
    ));

    if ($subMunicipalities > 0) {
        echo "{$subMunicipalities} of those are sub-municipalities linked to a parent city.\n";
    }

    if ($flagged !== []) {
        $count = count($flagged);
        $verb = $count === 1 ? 'row has' : 'rows have';
        echo "\n{$count} province-level {$verb} a blank Geographic Level. ".
            "They ship as provinces with isProvince false:\n";

        foreach ($flagged as $row) {
            echo "  row {$row['rowNumber']}  {$row['code']}  {$row['name']}\n";
        }

        echo "Check these against the PSA notes when updating the dataset.\n";
    }

    printf("\nPeak memory while reading the workbook: %.0f MB.\n", memory_get_peak_usage(true) / 1048576);
    echo 'Address::psgcVersion() is now '.PSGC_RELEASE_DATE.".\n";
}

try {
    main();
} catch (BuildError $error) {
    fwrite(STDERR, 'build-psgc: '.$error->getMessage()."\n");
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, $error."\n");
    exit(1);
}
