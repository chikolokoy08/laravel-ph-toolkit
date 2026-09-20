# Storing mobile numbers and TINs

Two casts, so a column holds one shape no matter what the customer typed.

| Cast | Stores |
| --- | --- |
| `AsPhMobileNumber` | E.164, for example `+639171234567`. |
| `AsPhTin` | The formatted TIN, for example `123-456-789-000`. |

## Why normalize at all

`0917 123 4567`, `(0917) 123-4567`, and `+63 917 123 4567` are one number
written three ways. Stored as typed, they are three different rows. Nothing
matches: not a lookup by number, not a unique index, not a deduplication pass.

Normalizing on write makes the column comparable:

```php
use Chikolokoy08\PhToolkit\Laravel\Casts\AsPhMobileNumber;
use Chikolokoy08\PhToolkit\Laravel\Casts\AsPhTin;
use Illuminate\Database\Eloquent\Model;

final class Contact extends Model
{
    protected function casts(): array
    {
        return [
            'mobile' => AsPhMobileNumber::class,
            'tin' => AsPhTin::class,
        ];
    }
}
```

```php
$contact = Contact::create([
    'mobile' => '(0917) 123-4567',
    'tin' => '123 456 789 000',
]);

$contact->mobile; // '+639171234567'
$contact->tin;    // '123-456-789-000'
```

With that in place a unique index does what you would expect:

```php
Schema::table('contacts', function (Blueprint $table): void {
    $table->unique('mobile');
});
```

## An invalid value throws

A column cast to one of these is meant to hold values that came back from the
formatter. Assigning something else throws `InvalidPhValue` rather than saving
it:

```php
use Chikolokoy08\PhToolkit\Exceptions\InvalidPhValue;

$contact->mobile = '0917123';
// InvalidPhValue: Cannot store "0917123" as a mobile number: it is not a valid
// Philippine mobile number. Validate the input with the PhMobileNumber rule,
// or Mobile::isValidMobileNumber(), before assigning it.
```

This is on purpose. A cast that quietly stored `null`, or the raw string, would
leave you with a column that is normalized most of the time, which is worse
than one that is never normalized. Nothing is written when the value is
rejected.

The rule and the cast are meant to be used together: the rule reports the
problem to the person filling the form, and the cast is the backstop for
everything that did not come through a form.

```php
$validated = $request->validate([
    'mobile' => ['required', new PhMobileNumber],
]);

$contact->update($validated); // Cannot throw: the rule already checked it.
```

If a value comes from somewhere you do not control, such as an import file or a
third-party API, check it first:

```php
use Chikolokoy08\PhToolkit\Mobile;

foreach ($rows as $row) {
    $mobile = Mobile::formatMobileNumber($row['mobile'] ?? null);

    if ($mobile === null) {
        $this->skipped[] = $row;

        continue;
    }

    Contact::create(['mobile' => $mobile]);
}
```

## Null

Null passes through untouched, so an optional column works normally:

```php
$contact->mobile = null;
$contact->mobile; // null
```

Make the column nullable if you intend to use that.

## What the exception says

`InvalidPhValue` extends `InvalidArgumentException`, so an existing handler for
bad arguments will already catch it. The message names the rule to validate
with, and never repeats raw input back:

| Value | Message describes it as |
| --- | --- |
| `'0917123'` | `"0917123"` |
| A very long string | The first 40 characters, then `...` |
| Bytes that are not valid UTF-8 | `a string that is not valid UTF-8` |
| `42` | `a value of type int` |

## Displaying the number

The cast stores E.164 and does not pretty-print. Format where you render:

```php
// '+639171234567' becomes '0917 123 4567'
$display = preg_replace('/\A\+63(\d{3})(\d{3})(\d{4})\z/', '0$1 $2 $3', $contact->mobile);
```

An accessor keeps that in one place:

```php
protected function mobileForDisplay(): Attribute
{
    return Attribute::get(fn (): ?string => $this->mobile === null
        ? null
        : preg_replace('/\A\+63(\d{3})(\d{3})(\d{4})\z/', '0$1 $2 $3', $this->mobile));
}
```

## Migrating a column that is already mixed

Existing rows are not touched by adding a cast. Normalize them once:

```php
use Chikolokoy08\PhToolkit\Mobile;

Contact::query()
    ->whereNotNull('mobile')
    ->chunkById(500, function ($contacts): void {
        foreach ($contacts as $contact) {
            $normalized = Mobile::formatMobileNumber($contact->getRawOriginal('mobile'));

            // Leave the ones that were never valid, and deal with them by hand.
            if ($normalized !== null) {
                $contact->updateQuietly(['mobile' => $normalized]);
            }
        }
    });
```

Run that before adding a unique index. Normalizing can turn rows that looked
different into duplicates.
