<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Laravel\Http;

use Chikolokoy08\PhToolkit\Address;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads the bundled PSGC dataset. No user data is touched and nothing is
 * written, so responses are cacheable by anyone and carry an ETag tied to the
 * PSGC release. Rate limiting is left to the middleware in
 * config/ph-toolkit.php.
 */
final class AddressController
{
    public function regions(Request $request): Response
    {
        return $this->respond($request, static fn (): array => Address::getRegions());
    }

    public function provinces(Request $request): Response
    {
        $region = self::code($request, 'region');

        return $this->respond($request, static fn (): array => $region === null
            ? Address::getProvinces()
            : Address::getProvincesByRegion($region));
    }

    public function cities(Request $request): Response
    {
        $filters = array_filter([
            'region' => self::code($request, 'region'),
            'province' => self::code($request, 'province'),
            'parent_city' => self::code($request, 'parent_city'),
        ], static fn (?string $value): bool => $value !== null);

        if (count($filters) > 1) {
            return self::problem(
                'Pass at most one of region, province, or parent_city. Got '.
                implode(', ', array_keys($filters)).'.',
            );
        }

        $withSub = $request->boolean('include_sub_municipalities');

        return $this->respond($request, static fn (): array => match (array_key_first($filters)) {
            'region' => Address::getCitiesByRegion($filters['region'] ?? null, $withSub),
            'province' => Address::getCitiesByProvince($filters['province'] ?? null, $withSub),
            // Sub-municipalities are city-level entries, so they are listed
            // here rather than on an endpoint of their own.
            'parent_city' => Address::getSubMunicipalitiesByCity($filters['parent_city'] ?? null),
            default => Address::getCities($withSub),
        });
    }

    public function barangays(Request $request): Response
    {
        $city = self::code($request, 'city');

        // Without a city this would serve all 42,010 barangays, which is never
        // what a form wants and is easy to request by accident.
        if ($city === null) {
            return self::problem('Pass a city code, for example ?city=0730600000.');
        }

        return $this->respond($request, static fn (): array => Address::getBarangaysByCity($city));
    }

    /**
     * Answers 304 before running the lookup when the client already has this
     * version of the answer. That matters most for barangays, where building
     * the response would otherwise load the whole 42,010-row file.
     *
     * @param  Closure(): array<int, mixed>  $data
     */
    private function respond(Request $request, Closure $data): Response
    {
        $etag = self::etag($request);

        if (in_array($etag, $request->getETags(), true) || in_array('*', $request->getETags(), true)) {
            return self::cacheable(new Response('', Response::HTTP_NOT_MODIFIED), $etag);
        }

        return self::cacheable(new JsonResponse($data()), $etag);
    }

    /**
     * The dataset only changes when the package is updated, so the PSGC
     * release plus the question being asked identifies the answer. Query
     * parameters are sorted, so the same question written two ways is one
     * cache entry.
     */
    private static function etag(Request $request): string
    {
        $query = $request->query();
        ksort($query);

        return '"'.hash('xxh128', implode('|', [
            Address::psgcVersion(),
            $request->path(),
            (string) json_encode($query),
        ])).'"';
    }

    private static function cacheable(Response $response, string $etag): Response
    {
        $maxAge = config('ph-toolkit.routes.cache_max_age');

        $response->setEtag($etag, false);
        $response->setPublic();
        $response->setMaxAge(is_int($maxAge) && $maxAge >= 0 ? $maxAge : 604800);

        return $response;
    }

    /**
     * Query values arrive as whatever was in the URL, so anything that is not
     * a non-empty string is treated as absent. An unknown code is not an error:
     * the lookups answer with an empty list, same as calling them directly.
     */
    private static function code(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** A rejected request is about the request, so it is never cached. */
    private static function problem(string $message): Response
    {
        return new JsonResponse(['message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
