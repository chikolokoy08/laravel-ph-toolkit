<div
    id="{{ $id }}"
    class="ph-address-selector"
    data-ph-address-selector
    data-endpoint="{{ $endpoint }}"
    data-include-sub-municipalities="{{ $includeSubMunicipalities ? '1' : '0' }}"
    data-region="{{ $region }}"
    data-province="{{ $province }}"
    data-city="{{ $city }}"
    data-barangay="{{ $barangay }}"
    {{ $attributes }}
>
    <div class="ph-address-selector__field">
        <label for="{{ $id }}-region">Region</label>
        <select
            id="{{ $id }}-region"
            name="{{ $fieldName('region') }}"
            data-level="region"
            @if ($required) required @endif
        >
            <option value="">Select a region</option>
        </select>
    </div>

    <div class="ph-address-selector__field" data-level-field="province" hidden>
        <label for="{{ $id }}-province">Province</label>
        <select id="{{ $id }}-province" name="{{ $fieldName('province') }}" data-level="province">
            <option value="">Select a province</option>
        </select>
    </div>

    <div class="ph-address-selector__field">
        <label for="{{ $id }}-city">City or municipality</label>
        <select
            id="{{ $id }}-city"
            name="{{ $fieldName('city') }}"
            data-level="city"
            disabled
            @if ($required) required @endif
        >
            <option value="">Select a city or municipality</option>
        </select>
    </div>

    <div class="ph-address-selector__field">
        <label for="{{ $id }}-barangay">Barangay</label>
        <select
            id="{{ $id }}-barangay"
            name="{{ $fieldName('barangay') }}"
            data-level="barangay"
            disabled
            @if ($required) required @endif
        >
            <option value="">Select a barangay</option>
        </select>
    </div>
</div>

@once
    <script>
        (function () {
            'use strict';

            var PLACEHOLDERS = {
                region: 'Select a region',
                province: 'Select a province',
                city: 'Select a city or municipality',
                barangay: 'Select a barangay'
            };

            function get(url) {
                return fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin'
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('ph-address-selector: ' + url + ' responded ' + response.status);
                    }

                    return response.json();
                });
            }

            function fill(select, items, selected) {
                var level = select.dataset.level;

                select.innerHTML = '';

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = PLACEHOLDERS[level];
                select.appendChild(placeholder);

                items.forEach(function (item) {
                    var option = document.createElement('option');
                    option.value = item.code;
                    // textContent, never innerHTML: place names are data.
                    option.textContent = item.name;
                    select.appendChild(option);
                });

                select.disabled = items.length === 0;
                select.value = selected && select.querySelector('option[value="' + selected + '"]') ? selected : '';
            }

            function clear(select) {
                fill(select, [], '');
            }

            function setup(root) {
                if (root.dataset.phAddressSelectorReady === '1') {
                    return;
                }

                root.dataset.phAddressSelectorReady = '1';

                var endpoint = root.dataset.endpoint;
                var withSub = root.dataset.includeSubMunicipalities === '1';
                var selects = {};

                ['region', 'province', 'city', 'barangay'].forEach(function (level) {
                    selects[level] = root.querySelector('[data-level="' + level + '"]');
                });

                var provinceField = root.querySelector('[data-level-field="province"]');

                function citiesUrl() {
                    var province = selects.province.value;
                    var query = province
                        ? 'province=' + encodeURIComponent(province)
                        : 'region=' + encodeURIComponent(selects.region.value);

                    return endpoint + '/cities?' + query + (withSub ? '&include_sub_municipalities=1' : '');
                }

                function loadCities(selected) {
                    if (!selects.region.value) {
                        clear(selects.city);
                        clear(selects.barangay);

                        return Promise.resolve();
                    }

                    var province = selects.province.value;

                    return get(citiesUrl()).then(function (cities) {
                        // With no province chosen, a region's list is narrowed to
                        // the cities that do not belong to one. That is what makes
                        // NCR and the highly urbanized cities reachable.
                        fill(selects.city, province ? cities : cities.filter(function (city) {
                            return city.provinceCode === null;
                        }), selected);
                    });
                }

                function loadBarangays(selected) {
                    if (!selects.city.value) {
                        clear(selects.barangay);

                        return Promise.resolve();
                    }

                    return get(endpoint + '/barangays?city=' + encodeURIComponent(selects.city.value))
                        .then(function (barangays) {
                            fill(selects.barangay, barangays, selected);
                        });
                }

                function loadProvinces(selected) {
                    if (!selects.region.value) {
                        clear(selects.province);
                        provinceField.hidden = true;

                        return Promise.resolve();
                    }

                    return get(endpoint + '/provinces?region=' + encodeURIComponent(selects.region.value))
                        .then(function (provinces) {
                            fill(selects.province, provinces, selected);
                            // NCR has none, so the step is dropped rather than
                            // shown empty.
                            provinceField.hidden = provinces.length === 0;
                        });
                }

                selects.region.addEventListener('change', function () {
                    loadProvinces('').then(function () {
                        return loadCities('');
                    }).then(function () {
                        clear(selects.barangay);
                    });
                });

                selects.province.addEventListener('change', function () {
                    loadCities('').then(function () {
                        clear(selects.barangay);
                    });
                });

                selects.city.addEventListener('change', function () {
                    loadBarangays('');
                });

                get(endpoint + '/regions').then(function (regions) {
                    fill(selects.region, regions, root.dataset.region);

                    if (!selects.region.value) {
                        return;
                    }

                    // Rebuild the chain below a value that was already chosen, so
                    // an edit form opens with its address in place.
                    return loadProvinces(root.dataset.province)
                        .then(function () {
                            return loadCities(root.dataset.city);
                        })
                        .then(function () {
                            return loadBarangays(root.dataset.barangay);
                        });
                }).catch(function (error) {
                    root.dataset.phAddressSelectorError = '1';
                    console.error(error);
                });
            }

            function init(scope) {
                (scope || document).querySelectorAll('[data-ph-address-selector]').forEach(setup);
            }

            window.PhAddressSelector = { init: init };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    init();
                });
            } else {
                init();
            }
        })();
    </script>
@endonce
