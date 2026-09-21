# Contributing

Thanks for taking the time. Bug reports, prefix corrections, and dataset
updates are all useful.

## Setup

PHP 8.2 or later. `ext-intl` is optional for the package but needed to run the
whole suite.

```bash
composer install
```

The address selector's browser script has its own suite, run with Node:

```bash
cd tests/js && npm ci && npm test
```

## Scripts

| Script | What it does |
| --- | --- |
| `composer test` | Runs the Pest suite. |
| `composer lint` | Checks code style with Pint. |
| `composer format` | Fixes code style with Pint. |
| `composer analyse` | Runs PHPStan through Larastan. |
| `composer check` | Style, static analysis, and tests. |
| `composer build:psgc` | Rebuilds the address dataset from the PSA workbook. See [docs/updating-psgc.md](docs/updating-psgc.md). |

Run `composer check` before opening a pull request. CI runs the same three
things across PHP 8.2 through 8.5 and Laravel 12 and 13.

## Coding standards

- `declare(strict_types=1)` in every file. Full parameter and return types.
- Final classes by default. Readonly properties where they fit.
- PSR-12 through Pint. Run `composer format`.
- PHPStan at level 10, with no baseline and no `@phpstan-ignore` annotations.
  If the analyser is unhappy, the fix is in the code.
- Naming: `isValidX` for validators returning a boolean, `formatX` for
  formatters, `getX` for lookups by code or parent, `findX` for name search.
- Formatters return `null` on invalid input. They do not throw. The Eloquent
  casts are the exception, and they throw `InvalidPhValue`.
- Docblocks on public methods: a short description and a realistic example.
  Examples in docblocks use named arguments for optional parameters.
- Comments explain why, never what.
- Use realistic Philippine examples in code, tests, and docs. Real city names,
  realistic but fake phone numbers and TINs. Never `foo` or `bar`.

### The core must stay framework free

`src/` holds the core classes, and none of them may reference Illuminate. The
Laravel integration lives under `src/Laravel/`. A test walks the source tree
and fails if that ever stops being true, which is also why the test suite is
split: the Core suite boots no application at all.

Runtime dependencies stay at `illuminate/support` and `illuminate/contracts`.
`ext-intl` is suggested, not required: only `Currency::formatPeso()` needs it,
and it throws a `MissingIntlExtension` naming the fix when it is absent.
Anything new that would need an extension has to degrade the same way, or not
need it. Everything else is a dev dependency.

## Tests

Pest with Orchestra Testbench, under `tests/`.

- `tests/Core/` runs on plain Pest with no framework booted.
- `tests/Laravel/` runs on Testbench.
- `tests/Support/` holds helpers, not tests.
- `tests/js/` holds the browser tests for the address selector's script, run
  with Node's test runner against jsdom. They read the script out of the Blade
  template, so they test what actually ships.

Package configuration that is read at boot, such as whether the address routes
exist, has to be set before the providers run. Use the `withPackageConfig()`
helper on the base TestCase rather than `config()->set()`, which is too late.

Cover real input: numbers with spaces and dashes, `+63` and `63` forms, empty
strings, whitespace, wrong lengths, letters mixed in.

## Parity with the npm package

This package is the PHP counterpart of
[ph-toolkit](https://github.com/chikolokoy08/ph-toolkit) on npm. The two are
meant to agree exactly: same accepted formats, same separator handling, same 08
prefix list, same TIN lengths, same formatter output, same PSGC release.

A change to validation or formatting behaviour in one belongs in both. If you
are proposing one here, say in the pull request what the JavaScript side should
do, or open an issue there too.

Two differences are deliberate, because PHP is not JavaScript:

- The validators take `?string` rather than accepting anything. Passing an
  array or an integer is a `TypeError`, where the JavaScript package returns
  `false`.
- PHP strings can hold bytes that are not valid UTF-8. Those are treated as
  invalid input. The case cannot arise in JavaScript.

## Changing the PSGC data

Do not hand-edit anything in `data/psgc/`. It is generated. See
[docs/updating-psgc.md](docs/updating-psgc.md).

Several tests assert exact counts, so they fail when the dataset changes. That
is the point. Update them with values you have confirmed against the PSA
workbook, and say in the pull request which release you built from.

## Translations

Messages live in `lang/{locale}/validation.php`. A test asserts every language
carries the same keys, so a new line has to be added everywhere at once.

Keep the wording plain. These messages are read by people filling in a form,
not by developers.

## Reporting a security problem

See [SECURITY.md](SECURITY.md). Do not open a public issue for one.
