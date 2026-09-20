# Validating form input

Three rules, each available as a rule object and as a string rule. They do the
same thing and produce the same message. Use whichever fits the surrounding
code.

| Field | Rule object | String rule | Accepts |
| --- | --- | --- | --- |
| Mobile number | `PhMobileNumber` | `ph_mobile` | 09 range, plus 0813, 0817, 0895 through 0898. Local, `63`, or `+63` form. |
| TIN | `PhTin` | `ph_tin` | 9, 12, or 14 digits. |
| ZIP code | `PhZipCode` | `ph_zip` | Exactly four digits. |

Mobile numbers and TINs ignore spaces, dashes, dots, and parentheses. You do
not need to strip anything before validating. ZIP codes only have surrounding
whitespace trimmed.

Validation is structural. A number can be well formed and still not be in
service, and a TIN can be well formed and belong to nobody. These rules catch
typos and paste errors, not fictional values.

## In a form request

```php
namespace App\Http\Requests;

use Chikolokoy08\PhToolkit\Laravel\Rules\PhMobileNumber;
use Chikolokoy08\PhToolkit\Laravel\Rules\PhTin;
use Chikolokoy08\PhToolkit\Laravel\Rules\PhZipCode;
use Illuminate\Foundation\Http\FormRequest;

final class StoreDeliveryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', new PhMobileNumber],
            'tin' => ['nullable', new PhTin],
            'zip_code' => ['required', new PhZipCode],
        ];
    }
}
```

The string rules read better in a short controller:

```php
$validated = $request->validate([
    'mobile' => ['required', 'ph_mobile'],
    'zip_code' => ['required', 'ph_zip'],
]);
```

## Null and optional fields

The rules treat `null` as invalid, so pair them with `nullable` when the field
is optional. That is Laravel's normal behaviour: `nullable` stops the other
rules from running on a null value.

```php
'tin' => ['nullable', new PhTin],       // empty is fine
'tin' => ['required', new PhTin],       // must be there and must be valid
```

Anything that is not a string fails rather than being coerced. An array, an
integer, or a string that is not valid UTF-8 all fail the same way a malformed
number does.

## Custom messages

Laravel's usual mechanisms work, and they take precedence over the messages
this package ships.

Per request:

```php
public function messages(): array
{
    return [
        'mobile.ph_mobile' => 'Kailangan namin ng numerong matatawagan, halimbawa 0917 123 4567.',
    ];
}
```

For a rule object, Laravel looks the key up by the snake case of the class
name, so `ph_mobile_number` rather than `ph_mobile`:

```php
'mobile.ph_mobile_number' => 'Enter a mobile number like 0917 123 4567.',
```

Application wide, in `lang/en/validation.php`:

```php
'custom' => [
    'mobile' => [
        'ph_mobile' => 'Enter a mobile number like 0917 123 4567.',
    ],
],
```

## Filipino translations

Messages ship in English and Filipino. Laravel picks the file that matches the
application locale, so nothing has to be wired up:

```php
app()->setLocale('fil');

// Dapat wastong numero ng mobile sa Pilipinas ang mobile.
```

The shipped lines:

| Key | English | Filipino |
| --- | --- | --- |
| `mobile` | The :attribute field must be a valid Philippine mobile number. | Dapat wastong numero ng mobile sa Pilipinas ang :attribute. |
| `tin` | The :attribute field must be a valid Philippine TIN. | Dapat wastong TIN ang :attribute. |
| `zip` | The :attribute field must be a valid Philippine ZIP code. | Dapat wastong ZIP code ang :attribute. |

To reword them, publish and edit:

```bash
php artisan vendor:publish --tag=ph-toolkit-translations
```

That writes `lang/vendor/ph-toolkit/en/validation.php` and
`lang/vendor/ph-toolkit/fil/validation.php`. To add another language, create
`lang/vendor/ph-toolkit/{locale}/validation.php` with the same three keys.

Give the fields readable names so the messages read well:

```php
public function attributes(): array
{
    return ['mobile' => 'mobile number', 'zip_code' => 'ZIP code'];
}
```

## Validating without the container

The rules wrap the core classes, which have no Laravel dependency and can be
called from anywhere, including a queue worker, a console script, or a package
of your own:

```php
use Chikolokoy08\PhToolkit\Mobile;
use Chikolokoy08\PhToolkit\Tin;
use Chikolokoy08\PhToolkit\Zip;

Mobile::isValidMobileNumber('0917 123 4567');  // true
Tin::isValidTin('123-456-789-000');            // true
Zip::isValidZipCode('6000');                   // true, Cebu City
```

The formatters both validate and normalize, so one call often replaces two:

```php
$mobile = Mobile::formatMobileNumber($request->input('mobile'));

if ($mobile === null) {
    // Not a valid number. It was never parseable, so there is nothing to keep.
}
```

## Amounts

`Currency::formatPeso()` takes a number, not a string, and returns `null`
rather than throwing when it cannot format:

```php
use Chikolokoy08\PhToolkit\Currency;

Currency::formatPeso(1234.5);                    // '₱1,234.50'
Currency::formatPeso(1234.5, decimals: 0);       // '₱1,235'
Currency::formatPeso(1234.5, locale: 'fil-PH');  // '₱1,234.50'
Currency::formatPeso(NAN);                       // null
```

`decimals` must be between 0 and 100, and the locale must be a valid BCP 47
tag. Anything else returns `null`.

## Validating an address

Address fields are a different problem. See
[the address selector](address-selector.md).
