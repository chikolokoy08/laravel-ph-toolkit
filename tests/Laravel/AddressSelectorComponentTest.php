<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;

function renderSelector(string $template = '<x-ph-address-selector />'): string
{
    return (string) Blade::render($template);
}

it('refuses to render while the address routes are off', function (): void {
    expect(config('ph-toolkit.routes.enabled'))->toBeFalse();

    try {
        renderSelector();
    } catch (ViewException $exception) {
        // Blade wraps whatever a component throws, so the cause is what says
        // which setting to turn on.
        expect($exception->getMessage())->toContain('PH_TOOLKIT_ROUTES_ENABLED')
            ->and($exception->getPrevious())->toBeInstanceOf(RuntimeException::class);

        return;
    }

    throw new RuntimeException('Expected the component to refuse to render.');
});

describe('with the routes turned on', function (): void {
    beforeEach(function (): void {
        $this->withPackageConfig(['ph-toolkit.routes.enabled' => true]);
    });

    it('renders a select for each level', function (string $level): void {
        expect(renderSelector())->toContain('data-level="'.$level.'"');
    })->with(['region', 'province', 'city', 'barangay']);

    it('names the fields as one array', function (): void {
        $html = renderSelector();

        expect($html)->toContain('name="address[region_code]"')
            ->and($html)->toContain('name="address[province_code]"')
            ->and($html)->toContain('name="address[city_code]"')
            ->and($html)->toContain('name="address[barangay_code]"');
    });

    it('accepts a different form name', function (): void {
        expect(renderSelector('<x-ph-address-selector name="shipping" />'))
            ->toContain('name="shipping[city_code]"')
            ->not->toContain('name="address[city_code]"');
    });

    it('points at the configured endpoint prefix', function (): void {
        expect(renderSelector())->toContain('data-endpoint="/ph-toolkit"');
    });

    it('follows a changed route prefix', function (): void {
        $this->withPackageConfig(['ph-toolkit.routes.prefix' => 'api/psgc']);

        expect(renderSelector())->toContain('data-endpoint="/api/psgc"');
    });

    it('carries preselected codes through to the markup', function (): void {
        $html = renderSelector(
            '<x-ph-address-selector region="0700000000" province="0702200000" city="0702201000" barangay="0702201001" />',
        );

        expect($html)->toContain('data-region="0700000000"')
            ->and($html)->toContain('data-province="0702200000"')
            ->and($html)->toContain('data-city="0702201000"')
            ->and($html)->toContain('data-barangay="0702201001"');
    });

    it('asks for sub-municipalities only when told to', function (): void {
        expect(renderSelector())->toContain('data-include-sub-municipalities="0"')
            ->and(renderSelector('<x-ph-address-selector :include-sub-municipalities="true" />'))
            ->toContain('data-include-sub-municipalities="1"');
    });

    it('marks the fields required when asked', function (): void {
        expect(renderSelector())->not->toContain('required')
            ->and(renderSelector('<x-ph-address-selector :required="true" />'))->toContain('required');
    });

    it('labels every select and ties the label to it', function (): void {
        $html = renderSelector();

        preg_match_all('/<select\s+id="([^"]+)"/', $html, $selects);
        preg_match_all('/<label for="([^"]+)"/', $html, $labels);

        expect($selects[1])->toHaveCount(4)
            ->and(array_diff($selects[1], $labels[1]))->toBe([]);
    });

    it('hides the province step until a region needs it', function (): void {
        expect(renderSelector())->toContain('data-level-field="province"')
            ->and(renderSelector())->toContain('hidden');
    });

    it('passes extra attributes through to the wrapper', function (): void {
        expect(renderSelector('<x-ph-address-selector class="grid gap-4" data-testid="picker" />'))
            ->toContain('data-testid="picker"')
            ->and(renderSelector('<x-ph-address-selector class="grid gap-4" />'))->toContain('grid gap-4');
    });

    it('ships the script once however many selectors are on the page', function (): void {
        $html = renderSelector('<x-ph-address-selector name="billing" /><x-ph-address-selector name="shipping" />');

        // data-endpoint appears once per rendered selector; the script block
        // mentions the selector attribute too, so it is not a useful counter.
        expect(substr_count($html, 'data-endpoint='))->toBe(2)
            ->and(substr_count($html, 'window.PhAddressSelector'))->toBe(1)
            ->and(substr_count($html, '<script>'))->toBe(1);
    });

    it('gives each selector on a page its own ids', function (): void {
        $html = renderSelector('<x-ph-address-selector name="billing" /><x-ph-address-selector name="shipping" />');

        preg_match_all('/<select\s+id="([^"]+)"/', $html, $matches);

        expect($matches[1])->toHaveCount(8)
            ->and(array_unique($matches[1]))->toHaveCount(8);
    });

    it('builds the option text as text, never as markup', function (): void {
        // Place names are data. If this ever becomes innerHTML, a name with
        // markup in it would be parsed instead of shown.
        $script = renderSelector();

        expect($script)->toContain('option.textContent = item.name')
            ->and($script)->not->toContain('innerHTML = item');
    });

    it('brings in no frontend dependency', function (): void {
        $html = renderSelector();

        expect($html)->not->toContain('<script src=')
            ->and($html)->not->toContain('cdn.')
            ->and($html)->not->toContain('import ');
    });
});
