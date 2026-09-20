<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Address;

it('registers no address routes by default', function (string $path): void {
    expect(config('ph-toolkit.routes.enabled'))->toBeFalse();

    $this->get($path)->assertNotFound();
})->with([
    'ph-toolkit/regions',
    'ph-toolkit/provinces',
    'ph-toolkit/cities',
    'ph-toolkit/barangays?city=0730600000',
]);

describe('with the routes turned on', function (): void {
    beforeEach(function (): void {
        $this->withPackageConfig(['ph-toolkit.routes.enabled' => true]);
    });

    it('lists the regions', function (): void {
        $response = $this->getJson('ph-toolkit/regions');

        $response->assertOk();

        expect($response->json())->toHaveCount(18)
            // PSGC order starts with NCR, not Region I.
            ->and($response->json('0'))->toBe(['code' => '1300000000', 'name' => 'National Capital Region (NCR)']);
    });

    it('lists the provinces of a region', function (): void {
        $response = $this->getJson('ph-toolkit/provinces?region=0700000000');

        $response->assertOk();

        expect(array_column($response->json(), 'name'))->toContain('Cebu', 'Bohol');
    });

    it('lists every province when no region is given', function (): void {
        expect($this->getJson('ph-toolkit/provinces')->json())->toHaveCount(84);
    });

    it('returns an empty list for an unknown code rather than an error', function (string $path): void {
        $response = $this->getJson($path);

        $response->assertOk();

        expect($response->json())->toBe([]);
    })->with([
        'ph-toolkit/provinces?region=9999999999',
        'ph-toolkit/cities?region=9999999999',
        'ph-toolkit/cities?province=9999999999',
        'ph-toolkit/barangays?city=9999999999',
    ]);

    it('lists the cities of a province', function (): void {
        expect($this->getJson('ph-toolkit/cities?province=0702200000')->json())->toHaveCount(50);
    });

    it('lists the cities of a region', function (): void {
        expect($this->getJson('ph-toolkit/cities?region=1300000000')->json())->toHaveCount(17);
    });

    it('leaves sub-municipalities out unless they are asked for', function (): void {
        $without = $this->getJson('ph-toolkit/cities?region=1300000000')->json();
        $with = $this->getJson('ph-toolkit/cities?region=1300000000&include_sub_municipalities=1')->json();

        expect($without)->toHaveCount(17)
            ->and($with)->toHaveCount(31)
            ->and(array_column($with, 'name'))->toContain('Tondo I/II')
            ->and(array_column($without, 'name'))->not->toContain('Tondo I/II');
    });

    it('lists the districts of a city', function (): void {
        $response = $this->getJson('ph-toolkit/cities?parent_city=1380600000');

        expect($response->json())->toHaveCount(14)
            ->and(array_column($response->json(), 'name'))->toContain('Intramuros');
    });

    it('lists every city when nothing is given', function (): void {
        expect($this->getJson('ph-toolkit/cities')->json())->toHaveCount(1642);
    });

    it('refuses more than one city filter', function (): void {
        $response = $this->getJson('ph-toolkit/cities?region=1300000000&province=0702200000');

        $response->assertStatus(422);

        expect($response->json('message'))->toContain('at most one');
    });

    it('lists the barangays of a city', function (): void {
        expect($this->getJson('ph-toolkit/barangays?city=0730600000')->json())->toHaveCount(80);
    });

    it('rolls Manila barangays up from its districts', function (): void {
        expect($this->getJson('ph-toolkit/barangays?city=1380600000')->json())->toHaveCount(897);
    });

    it('refuses to serve every barangay at once', function (): void {
        $response = $this->getJson('ph-toolkit/barangays');

        $response->assertStatus(422);

        expect($response->json('message'))->toContain('city code');
    });

    it('serves the same shape the lookups return', function (): void {
        expect($this->getJson('ph-toolkit/cities?province=0702200000')->json())
            ->toBe(json_decode((string) json_encode(Address::getCitiesByProvince('0702200000')), true))
            ->and($this->getJson('ph-toolkit/barangays?city=0730600000')->json())
            ->toBe(json_decode((string) json_encode(Address::getBarangaysByCity('0730600000')), true));
    });

    it('allows the response to be cached', function (string $path): void {
        $response = $this->getJson($path);

        $response->assertOk();

        expect($response->headers->getCacheControlDirective('public'))->toBeTrue()
            ->and($response->headers->getCacheControlDirective('max-age'))->toBe('604800')
            ->and($response->headers->get('ETag'))->toBeString()
            ->and($response->headers->get('ETag'))->toStartWith('"');
    })->with([
        'ph-toolkit/regions',
        'ph-toolkit/provinces?region=0700000000',
        'ph-toolkit/cities?province=0702200000',
        'ph-toolkit/barangays?city=0730600000',
    ]);

    it('follows a configured max age', function (): void {
        $this->withPackageConfig(['ph-toolkit.routes.cache_max_age' => 60]);

        expect($this->getJson('ph-toolkit/regions')->headers->getCacheControlDirective('max-age'))->toBe('60');
    });

    it('answers 304 when the client already has the response', function (string $path): void {
        $etag = $this->getJson($path)->headers->get('ETag');

        $response = $this->withHeaders(['If-None-Match' => (string) $etag])->getJson($path);

        $response->assertStatus(304);

        expect($response->getContent())->toBe('')
            ->and($response->headers->get('ETag'))->toBe($etag);
    })->with([
        'ph-toolkit/regions',
        'ph-toolkit/cities?region=1300000000',
        'ph-toolkit/barangays?city=1380600000',
    ]);

    it('answers 304 for a wildcard If-None-Match', function (): void {
        $this->withHeaders(['If-None-Match' => '*'])->getJson('ph-toolkit/regions')->assertStatus(304);
    });

    it('sends the body when the client holds a different version', function (): void {
        $response = $this->withHeaders(['If-None-Match' => '"not-the-current-dataset"'])
            ->getJson('ph-toolkit/regions');

        $response->assertOk();

        expect($response->json())->toHaveCount(18);
    });

    it('gives different questions different etags', function (): void {
        $regions = $this->getJson('ph-toolkit/regions')->headers->get('ETag');
        $cebu = $this->getJson('ph-toolkit/cities?province=0702200000')->headers->get('ETag');
        $ncr = $this->getJson('ph-toolkit/cities?region=1300000000')->headers->get('ETag');

        expect([$regions, $cebu, $ncr])->toHaveCount(3)
            ->and(array_unique([$regions, $cebu, $ncr]))->toHaveCount(3);
    });

    it('gives the same question the same etag however the query is ordered', function (): void {
        $first = $this->getJson('ph-toolkit/cities?region=1300000000&include_sub_municipalities=1')
            ->headers->get('ETag');
        $second = $this->getJson('ph-toolkit/cities?include_sub_municipalities=1&region=1300000000')
            ->headers->get('ETag');

        expect($first)->toBe($second);
    });

    it('ties the etag to the PSGC version', function (): void {
        $etag = (string) $this->getJson('ph-toolkit/regions')->headers->get('ETag');
        $expected = '"'.hash('xxh128', implode('|', [
            Address::psgcVersion(),
            'ph-toolkit/regions',
            (string) json_encode([]),
        ])).'"';

        expect($etag)->toBe($expected);
    });

    it('does not cache a rejected request', function (string $path): void {
        $response = $this->getJson($path);

        $response->assertStatus(422);

        // An absent directive reads as null, not false.
        expect($response->headers->get('ETag'))->toBeNull()
            ->and($response->headers->getCacheControlDirective('public'))->toBeNull()
            ->and($response->headers->getCacheControlDirective('max-age'))->toBeNull();
    })->with([
        'ph-toolkit/barangays',
        'ph-toolkit/cities?region=1300000000&province=0702200000',
    ]);

    it('rate limits by default without any application setup', function (): void {
        expect(config('ph-toolkit.routes.middleware'))->toBe(['throttle:60,1']);

        $response = $this->getJson('ph-toolkit/regions');

        $response->assertOk();

        expect($response->headers->get('X-RateLimit-Limit'))->toBe('60')
            ->and($response->headers->get('X-RateLimit-Remaining'))->toBe('59');
    });

    it('names its routes', function (): void {
        expect(route('ph-toolkit.regions', absolute: false))->toBe('/ph-toolkit/regions')
            ->and(route('ph-toolkit.barangays', absolute: false))->toBe('/ph-toolkit/barangays');
    });
});

describe('with a custom prefix', function (): void {
    beforeEach(function (): void {
        $this->withPackageConfig([
            'ph-toolkit.routes.enabled' => true,
            'ph-toolkit.routes.prefix' => 'api/psgc',
        ]);
    });

    it('serves under the configured prefix', function (): void {
        $this->getJson('api/psgc/regions')->assertOk();
        $this->getJson('ph-toolkit/regions')->assertNotFound();
    });
});
