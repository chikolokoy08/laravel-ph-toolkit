<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Laravel\View\Components;

use Illuminate\View\Component;
use RuntimeException;

/**
 * A cascading region, province, city, and barangay picker.
 *
 * It reads the JSON endpoints, so config('ph-toolkit.routes.enabled') has to
 * be true for it to work. The four selects post as an array under one name,
 * which a form request reads as address.region_code and so on.
 *
 * @example
 * <x-ph-address-selector name="address" :city="$order->city_code" />
 */
final class AddressSelector extends Component
{
    public readonly string $endpoint;

    public readonly string $id;

    /**
     * @param  string  $name  Form name the four selects are nested under.
     * @param  bool  $includeSubMunicipalities  List the 14 districts of the City
     *                                          of Manila alongside the cities. Off by default, because
     *                                          getBarangaysByCity already answers for the whole city.
     */
    public function __construct(
        public readonly string $name = 'address',
        public readonly ?string $region = null,
        public readonly ?string $province = null,
        public readonly ?string $city = null,
        public readonly ?string $barangay = null,
        public readonly bool $includeSubMunicipalities = false,
        public readonly bool $required = false,
    ) {
        if (config('ph-toolkit.routes.enabled') !== true) {
            throw new RuntimeException(
                'The <x-ph-address-selector /> component reads the ph-toolkit address endpoints, '.
                'which are disabled. Set PH_TOOLKIT_ROUTES_ENABLED=true, or routes.enabled in '.
                'config/ph-toolkit.php, to turn them on.',
            );
        }

        $prefix = config('ph-toolkit.routes.prefix');

        $this->endpoint = '/'.trim(is_string($prefix) ? $prefix : 'ph-toolkit', '/');
        $this->id = 'ph-address-'.substr(md5($name.'|'.spl_object_hash($this)), 0, 8);
    }

    /**
     * Blade resolves a returned name against the registered view paths, so the
     * component does not have to reach for the view factory itself.
     */
    public function render(): string
    {
        return 'ph-toolkit::components.address-selector';
    }

    /**
     * The form name for one level, so the selects post together as one array.
     */
    public function fieldName(string $level): string
    {
        return $this->name.'['.$level.'_code]';
    }
}
