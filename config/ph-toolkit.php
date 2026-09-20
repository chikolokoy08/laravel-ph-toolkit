<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Address routes
    |--------------------------------------------------------------------------
    |
    | JSON endpoints for regions, provinces, cities, and barangays, used by the
    | <x-ph-address-selector /> component. They are off by default: turn them
    | on only if something in the browser needs to read the PSGC data.
    |
    */

    'routes' => [

        'enabled' => (bool) env('PH_TOOLKIT_ROUTES_ENABLED', false),

        'prefix' => env('PH_TOOLKIT_ROUTES_PREFIX', 'ph-toolkit'),

        /*
         | Read-only JSON over a bundled dataset: no sessions, no cookies, and
         | nothing to protect with CSRF, so the web group would only add cost.
         | Rate limiting is the one thing worth having. Add 'auth' here to keep
         | the endpoints behind a login, or 'web' if you need session state.
         */
        'middleware' => ['throttle:60,1'],

        /*
         | How long a browser may reuse a response, in seconds. The data only
         | changes when this package is updated, so this can be generous: a
         | week by default. Responses also carry an ETag derived from the PSGC
         | version, so a client that revalidates gets a 304 rather than the
         | body again.
         */
        'cache_max_age' => (int) env('PH_TOOLKIT_ROUTES_CACHE_MAX_AGE', 604800),

    ],

];
