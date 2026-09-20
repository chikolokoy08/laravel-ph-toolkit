# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - Unreleased

First release. The PHP and Laravel counterpart of the
[ph-toolkit](https://github.com/chikolokoy08/ph-toolkit) npm package, with
matching behaviour.

### Added

- `Mobile::isValidMobileNumber()` and `Mobile::formatMobileNumber()`.
  Validation covers the 09 range and the known 08 mobile blocks (0813, 0817,
  0895 through 0898), each in local, 63, and +63 form. Formatting normalizes to
  E.164.
- `Tin::isValidTin()` and `Tin::formatTin()`. Accepts 9, 12, and 14 digits,
  covering the base TIN and both the 3-digit and 5-digit branch code forms.
  Formats as XXX-XXX-XXX, XXX-XXX-XXX-XXX, or XXX-XXX-XXX-XXXXX.
- `Zip::isValidZipCode()`. Structural check for exactly four digits.
- `Currency::formatPeso()`, wrapping `NumberFormatter` with `locale` and
  `decimals` options. ICU's rounding mode is set to half away from zero, which
  is what `Intl.NumberFormat` does and what keeps the output identical to the
  npm package's.
- Mobile numbers and TINs ignore spaces, dashes, dots, and parentheses,
  including the Unicode spaces and the byte order mark that JavaScript's `\s`
  covers and PCRE's does not.
- Formatters return `null` on invalid input instead of throwing.
- `Address`, carrying the full PSGC: lookups by code and by parent,
  accent-insensitive name search, and `Address::psgcVersion()`. Each level is
  read from disk on first use, so validating a phone number never loads the
  42,010 barangays.
- Provinces carry `isProvince`. It is false for the two PSA entries that hold a
  province code without being provinces, "City of Isabela (Not a Province)" and
  "Special Geographic Area", which are kept so the places under them stay
  reachable.
- Cities carry `parentCityCode`, set for the 14 sub-municipalities of the City
  of Manila and null everywhere else. `getCities()`, `getCitiesByRegion()`, and
  `getCitiesByProvince()` leave sub-municipalities out unless called with
  `includeSubMunicipalities: true`, `getSubMunicipalitiesByCity()` lists them,
  and `getBarangaysByCity()` for the City of Manila returns all 897 barangays
  across its districts.
- `bin/build-psgc.php`, which converts the PSA PSGC publication workbook into
  the PHP arrays the address lookups ship. It reads the workbook in row chunks
  to stay inside PHP's default memory limit, and its output is deterministic.
- The `PhMobileNumber`, `PhTin`, and `PhZipCode` rule objects, and the
  `ph_mobile`, `ph_tin`, and `ph_zip` string rules.
- Validation messages in English and Filipino, publishable with
  `--tag=ph-toolkit-translations`.
- `AsPhMobileNumber` and `AsPhTin` Eloquent casts, storing E.164 and the
  formatted TIN. An invalid value throws `InvalidPhValue` on set rather than
  being written.
- The `PhToolkit` facade, carrying the same six methods the npm package exports
  from its root entry point.
- Opt-in JSON endpoints for regions, provinces, cities, and barangays, with a
  configurable prefix and middleware. Off by default. Responses carry an ETag
  derived from the PSGC release and answer 304 to a matching `If-None-Match`.
- The `<x-ph-address-selector />` Blade component, a cascading dropdown in
  plain JavaScript with no frontend dependency.
- Package version and PSGC release reported in `php artisan about`.
- Publishable config, translations, and views.

### Notes

- Requires `ext-intl`, which is what allows `Currency::formatPeso()` to match
  the npm package byte for byte and name search to fold accents.
- Validators take `?string` and formatters take `?string` or
  `int|float|null`. `null` is invalid input, and anything else is a
  `TypeError`. The npm package accepts anything and returns `false` or `null`,
  because JavaScript has no way to enforce this at the boundary.
- A string holding bytes that are not valid UTF-8 is treated as invalid input:
  `false` from a validator, `null` from a formatter, and `InvalidPhValue` from
  a cast. The case cannot arise in JavaScript.
- Built from PSGC 2Q 2026, publication date June 30, 2026 (per the PSA
  datafile), announced by PSA on July 13, 2026.

[0.1.0]: https://github.com/chikolokoy08/laravel-ph-toolkit/releases/tag/v0.1.0
