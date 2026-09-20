# The address selector

A region, province, city, and barangay picker. The data ships with the package,
so there is nothing to seed and nothing to fetch from anyone else.

There are two ways to use it: server side through `Address`, or in the browser
through the JSON endpoints and the Blade component.

## Server side

Every lookup is synchronous and reads bundled data:

```php
use Chikolokoy08\PhToolkit\Address;

Address::getRegions();                               // 18 regions
Address::getProvincesByRegion('0700000000');         // Bohol and Cebu
Address::getCitiesByProvince('0702200000');          // 50 in Cebu
Address::getBarangaysByCity('0730600000');           // 80 in City of Cebu
Address::getCityByCode('0730600000')?->name;         // 'City of Cebu'
Address::findCitiesByName('paranaque');              // City of Parañaque
```

Each level is read from disk the first time something asks for it. Listing
regions does not load the 42,010 barangays. Loading the full barangay set costs
about 12 MB, or 7.3 MB when opcache is sharing the array between requests.

Name search is case- and accent-insensitive, so `'paranaque'` matches
`'Parañaque'`.

## Two things that make a naive cascade wrong

**Regions without provinces.** NCR has none. Its 17 cities and municipalities
sit directly under the region, so `getProvincesByRegion('1300000000')` is
empty. The same applies to highly urbanized cities elsewhere: City of Cebu,
City of Baguio, and City of Zamboanga are independent of the provinces around
them, their `provinceCode` is null, and they do not appear in
`getCitiesByProvince`. There are 34 such cities and municipalities.

Handle both by skipping the province step when a region has none, and by
listing a region's province-less cities when no province is chosen:

```php
function citiesFor(?string $regionCode, ?string $provinceCode): array
{
    if ($provinceCode !== null) {
        return Address::getCitiesByProvince($provinceCode);
    }

    if ($regionCode === null) {
        return [];
    }

    // No province chosen: the cities that do not belong to one.
    return array_values(array_filter(
        Address::getCitiesByRegion($regionCode),
        fn ($city): bool => $city->provinceCode === null,
    ));
}
```

**The City of Manila and its districts.** Manila has no barangays of its own.
Its 897 barangays belong to 14 sub-municipalities, the districts established
under RA 409: Tondo I/II, Binondo, Quiapo, San Nicolas, Santa Cruz, Sampaloc,
San Miguel, Ermita, Intramuros, Malate, Paco, Pandacan, Port Area, and Santa
Ana.

You do not have to care about this. `getCities`, `getCitiesByRegion`, and
`getCitiesByProvince` leave sub-municipalities out, so Metro Manila lists the
City of Manila once. And `getBarangaysByCity('1380600000')` returns all 897
barangays across the districts, so a region to city to barangay cascade works
in Manila with no special case.

If you do want a district step, add one. It appears only for Manila, because
every other city returns an empty array:

```php
$districts = Address::getSubMunicipalitiesByCity($cityCode); // 14, or none

// Barangays for the chosen district, or all of Manila's when none is chosen.
$barangays = Address::getBarangaysByCity($districtCode ?? $cityCode);
```

To list the districts inline with the cities instead, ask for them:

```php
Address::getCitiesByRegion('1300000000', includeSubMunicipalities: true); // 31
Address::getCities(includeSubMunicipalities: true);                      // 1656, up from 1642
```

## Entries that hold a province code without being provinces

Two rows in the PSGC sit at the province level without being provinces:

- `0990100000` "City of Isabela (Not a Province)" under Region IX, which
  contains City of Isabela.
- `1999900000` "Special Geographic Area" under BARMM, which contains 8
  municipalities.

They ship as provinces so the places under them stay reachable through a normal
cascade, and their names are left exactly as the PSA publishes them so the
dataset can be diffed against a future release. Each has `isProvince` set to
false.

Filter them out if you only want real provinces:

```php
array_filter(
    Address::getProvincesByRegion('0900000000'),
    fn ($province): bool => $province->isProvince,
);
```

Or relabel them in the dropdown while keeping them selectable:

```blade
@foreach ($provinces as $province)
    <option value="{{ $province->code }}">
        {{ $province->isProvince
            ? $province->name
            : Str::replaceLast(' (Not a Province)', ' (city)', $province->name) }}
    </option>
@endforeach
```

## The JSON endpoints

Off by default. Turn them on when something in the browser needs the data:

```dotenv
PH_TOOLKIT_ROUTES_ENABLED=true
```

Or publish the config and set `routes.enabled`:

```bash
php artisan vendor:publish --tag=ph-toolkit-config
```

```php
'routes' => [
    'enabled' => (bool) env('PH_TOOLKIT_ROUTES_ENABLED', false),
    'prefix' => env('PH_TOOLKIT_ROUTES_PREFIX', 'ph-toolkit'),
    'middleware' => ['throttle:60,1'],
    'cache_max_age' => (int) env('PH_TOOLKIT_ROUTES_CACHE_MAX_AGE', 604800),
],
```

Four endpoints, under the configured prefix:

| Endpoint | Query | Returns |
| --- | --- | --- |
| `GET /regions` | | All 18 regions. |
| `GET /provinces` | `region` | Provinces, all of them or one region's. |
| `GET /cities` | `region`, `province`, `parent_city`, `include_sub_municipalities` | Cities and municipalities. |
| `GET /barangays` | `city` (required) | The barangays of one city. |

```bash
curl localhost/ph-toolkit/regions
curl localhost/ph-toolkit/provinces?region=0700000000
curl localhost/ph-toolkit/cities?province=0702200000
curl 'localhost/ph-toolkit/cities?parent_city=1380600000'   # the 14 Manila districts
curl localhost/ph-toolkit/barangays?city=0730600000
```

The response is a JSON array in the same shape the lookups return. One entry
from the last of those looks like this:

```json
{"code":"0730600041","name":"Lahug","cityCode":"0730600000"}
```

Two requests are refused with a 422 and a message:

- `/barangays` without a `city`. That would serve all 42,010 barangays, which
  is never what a form wants and is easy to ask for by accident.
- `/cities` with more than one of `region`, `province`, and `parent_city`,
  rather than silently picking one.

An unknown code is not an error. It returns an empty array, the same as calling
the lookup directly.

### Middleware

The default is `['throttle:60,1']`: 60 requests a minute per client. These are
read-only endpoints over bundled data, with no session, no cookies, and nothing
to protect with CSRF, so the `web` group would only add cost. `throttle` is
registered by Laravel out of the box, so nothing has to be installed for it to
work.

Change it if your app needs something else:

```php
'middleware' => ['auth', 'throttle:120,1'],  // behind a login
'middleware' => ['web'],                     // if you need session state
'middleware' => [],                          // no middleware at all
```

### Caching

Responses are cacheable and revalidate cheaply. Each one carries:

```http
Cache-Control: max-age=604800, public
ETag: "e857e8daa9f36addc503367bd70ee867"
```

A week by default, because the data only changes when this package is updated.
Change it with `PH_TOOLKIT_ROUTES_CACHE_MAX_AGE` or `routes.cache_max_age`.

The ETag is derived from the PSGC release and the question being asked, so it
changes when the dataset changes and not otherwise. Query parameters are
sorted first, so the same question written two ways is one cache entry.

A request carrying a matching `If-None-Match` gets a 304 with no body:

```bash
curl -H 'If-None-Match: "e857e8daa9f36addc503367bd70ee867"' localhost/ph-toolkit/regions -i
# HTTP/1.1 304 Not Modified
```

The 304 is answered before the lookup runs, so a revalidated request for
Manila's barangays never loads the barangay file at all.

The two 422 responses carry no cache headers.

## The Blade component

```blade
<x-ph-address-selector />
```

Four cascading selects, in plain JavaScript, with no frontend dependency and no
build step. It reads the endpoints above, so `routes.enabled` has to be true.
If it is not, the component throws and says which setting to turn on rather
than rendering a dropdown that never fills.

The selects post as one array:

```php
$request->input('address.region_code');
$request->input('address.city_code');
$request->input('address.barangay_code');
```

| Attribute | Default | What it does |
| --- | --- | --- |
| `name` | `address` | Form name the four selects nest under. |
| `region`, `province`, `city`, `barangay` | none | Preselected PSGC codes. |
| `include-sub-municipalities` | `false` | List Manila's districts alongside the cities. |
| `required` | `false` | Marks region, city, and barangay required. |

```blade
<x-ph-address-selector
    name="shipping"
    :region="old('shipping.region_code', $order->region_code)"
    :province="old('shipping.province_code', $order->province_code)"
    :city="old('shipping.city_code', $order->city_code)"
    :barangay="old('shipping.barangay_code', $order->barangay_code)"
    required
/>
```

Preselected codes rebuild the whole chain on load, so an edit form opens with
the address in place. The province step hides itself for regions that have
none, and with no province chosen the city list narrows to that region's
province-less cities. Manila needs nothing special.

The component ships no CSS. Style it through `.ph-address-selector` and
`.ph-address-selector__field`, or pass your own classes, which are forwarded to
the wrapper:

```blade
<x-ph-address-selector class="grid gap-4 sm:grid-cols-2" />
```

The script is included once per page no matter how many selectors are on it. If
you add one to the DOM after load, initialize it:

```js
window.PhAddressSelector.init(container);
```

To change the markup, publish the view:

```bash
php artisan vendor:publish --tag=ph-toolkit-views
```

## Validating what was submitted

The codes are the thing to check. There is no rule for this, because what
counts as valid depends on how much of the address you require:

```php
use Chikolokoy08\PhToolkit\Address;
use Illuminate\Validation\Rule;

public function rules(): array
{
    return [
        'address.region_code' => ['required', Rule::in(
            array_map(fn ($region): string => $region->code, Address::getRegions()),
        )],
        'address.city_code' => ['required', 'string'],
        'address.barangay_code' => ['required', 'string'],
    ];
}

public function after(): array
{
    return [function ($validator): void {
        $barangay = Address::getBarangayByCode($this->input('address.barangay_code'));

        if ($barangay === null) {
            $validator->errors()->add('address.barangay_code', 'Select a barangay from the list.');

            return;
        }

        // The barangay has to sit under the chosen city. For the City of
        // Manila that means one of its districts, so compare against the list
        // rather than the code.
        $codes = array_map(
            fn ($b): string => $b->code,
            Address::getBarangaysByCity($this->input('address.city_code')),
        );

        if (! in_array($barangay->code, $codes, true)) {
            $validator->errors()->add('address.barangay_code', 'That barangay is not in the city you chose.');
        }
    }];
}
```

## Store codes, not names

Names change. The PSGC is republished quarterly and places get renamed, merged,
and reclassified. Codes are stable, and you can resolve a name at display time:

```php
Address::getCityByCode($order->city_code)?->name;
```

Record `Address::psgcVersion()` alongside the address if you need to know which
release a stored code came from.
