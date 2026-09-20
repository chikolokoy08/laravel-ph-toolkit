# laravel-ph-toolkit

Validators, formatters, and PSGC address data for Philippine apps, with Laravel
validation rules, Eloquent casts, and an address selector.

## Install

```bash
composer require chikolokoy08/laravel-ph-toolkit
```

The package registers itself. Publish the config and translations only if you
need to change them:

```bash
php artisan vendor:publish --tag=ph-toolkit-config
php artisan vendor:publish --tag=ph-toolkit-translations
```

## Quick start

Validate a mobile number in a form request:

```php
use Chikolokoy08\PhToolkit\Laravel\Rules\PhMobileNumber;

public function rules(): array
{
    return [
        'mobile' => ['required', new PhMobileNumber],
        'tin' => ['nullable', 'ph_tin'],
        'zip_code' => ['required', 'ph_zip'],
    ];
}
```

Store a mobile number in one shape, whatever the customer typed:

```php
use Chikolokoy08\PhToolkit\Laravel\Casts\AsPhMobileNumber;

protected function casts(): array
{
    return ['mobile' => AsPhMobileNumber::class];
}

$contact->mobile = '(0917) 123-4567';
$contact->mobile; // '+639171234567'
```

Look up an address:

```php
use Chikolokoy08\PhToolkit\Address;

Address::getRegions();                          // 18 regions
Address::getCitiesByProvince('0702200000');     // 50 cities and municipalities of Cebu
Address::getBarangaysByCity('0730600000');      // 80 barangays of City of Cebu
Address::findCitiesByName('paranaque');         // City of Parañaque
```

Use the core classes anywhere, with or without Laravel:

```php
use Chikolokoy08\PhToolkit\Currency;
use Chikolokoy08\PhToolkit\Mobile;
use Chikolokoy08\PhToolkit\Tin;

Mobile::isValidMobileNumber('0917 123 4567');  // true
Tin::formatTin('123456789000');                // '123-456-789-000'
Currency::formatPeso(1234.5);                  // '₱1,234.50'
```

## What you get

| | |
| --- | --- |
| Core classes | `Mobile`, `Tin`, `Zip`, `Currency`, `Address`. No Illuminate dependency. |
| Validation rules | `PhMobileNumber`, `PhTin`, `PhZipCode`, and the string rules `ph_mobile`, `ph_tin`, `ph_zip`. |
| Translations | English and Filipino, publishable. |
| Eloquent casts | `AsPhMobileNumber` stores E.164, `AsPhTin` stores the formatted TIN. |
| Facade | `PhToolkit`, carrying the six core methods. |
| Address data | The full PSGC: 18 regions, 84 province-level entries, 1,656 cities and municipalities, 42,010 barangays. |
| Address routes | Opt-in JSON endpoints, with a configurable prefix and middleware. |
| Blade component | `<x-ph-address-selector />`, a cascading dropdown with no frontend dependency. |
| `php artisan about` | Reports the package version and the PSGC release. |

## What counts as valid

**Mobile numbers.** The 09 range, plus the known mobile blocks outside it:
0813, 0817, 0895, 0896, 0897, and 0898. Each is accepted in local, `63`, and
`+63` form. Validation is structural. Prefixes are not checked against network
ranges, because number portability means a prefix no longer identifies a
network. Every other 08 prefix is rejected.

**TINs.** 9 digits for a base TIN, 12 with a 3-digit branch code, and 14 with
the 5-digit branch code used on newer BIR forms. Any other length is rejected.
The BIR publishes no check-digit rule, so there is none here.

**ZIP codes.** Exactly four digits. Not checked against the PhilPost directory.

**Separators.** Mobile numbers and TINs ignore spaces, dashes, dots, and
parentheses, so `'0917 123 4567'`, `'0917-123-4567'`, and `'(0917) 123.4567'`
are the same number. Letters and any other character fail. ZIP codes are
stricter: surrounding whitespace is trimmed, but `'1 000'` and `'10-00'` are
rejected, because ZIP codes are not written in groups.

**Return values.** Validators return a boolean. Formatters return `null` on
invalid input rather than throwing. The casts are the exception: they throw
`InvalidPhValue` rather than writing a value they cannot normalize.

**Input types.** Validators and formatters take `?string` and treat `null` as
invalid. Anything else is a `TypeError`, which is what the signature is for.
`Currency::formatPeso()` takes `int|float|null`.

**Invalid UTF-8.** PHP strings are byte strings, so a caller can pass bytes
that are not valid UTF-8. Validators return `false` and formatters return
`null` for those, the same as any other invalid input. The casts throw. This
case does not exist in the JavaScript package, where strings are always
well formed.

## Documentation

- [Validating form input](docs/validation.md)
- [Storing mobile numbers and TINs](docs/casts.md)
- [The address selector](docs/address-selector.md)
- [Updating the PSGC dataset](docs/updating-psgc.md)

## Supported versions

| | |
| --- | --- |
| PHP | 8.2, 8.3, 8.4, 8.5 |
| Laravel | 12, 13 |

PHP 8.2 is the floor because Laravel 12 still supports it. `ext-intl` is
required: `Currency::formatPeso()` uses `NumberFormatter`, and name search
folds accents with `Normalizer`.

## Data source

The address data comes from the Philippine Standard Geographic Code (PSGC),
published by the Philippine Statistics Authority. This release is built from
**PSGC 2Q 2026, publication date June 30, 2026 (per the PSA datafile),
announced by PSA on July 13, 2026**. The publication date is what
`Address::psgcVersion()` returns.

The PSA asks that it be acknowledged as the source, which this notice does. The
PSA publishes the PSGC quarterly and places it under no access constraints.
This package is not affiliated with or endorsed by the PSA.

Two points where the data is not as tidy as it looks:

- 34 cities and municipalities are independent of any province and have a null
  `provinceCode`: the 17 in NCR, and 17 highly urbanized cities elsewhere,
  among them City of Cebu, City of Baguio, and City of Zamboanga. Reach them
  with `Address::getCitiesByRegion()`.
- Two entries hold a province code without being provinces: "City of Isabela
  (Not a Province)" and "Special Geographic Area". They ship as provinces with
  `isProvince` set to false, under the names the PSA publishes.

Both are covered in [the address selector guide](docs/address-selector.md).

## A JavaScript version exists

The same validators, formatters, and PSGC data are available for JavaScript and
TypeScript as [ph-toolkit](https://www.npmjs.com/package/@chikolokoy08/ph-toolkit)
on npm. The two are kept behaviourally identical: same accepted formats, same
separator handling, same prefix list, same PSGC release. If you validate a
number in the browser and again on the server, both will agree.

## License

MIT. See [LICENSE](LICENSE).
