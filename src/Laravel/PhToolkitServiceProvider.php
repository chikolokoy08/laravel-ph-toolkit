<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Laravel;

use Chikolokoy08\PhToolkit\Address;
use Chikolokoy08\PhToolkit\Laravel\View\Components\AddressSelector;
use Chikolokoy08\PhToolkit\Mobile;
use Chikolokoy08\PhToolkit\Tin;
use Chikolokoy08\PhToolkit\Zip;
use Composer\InstalledVersions;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Validator;

final class PhToolkitServiceProvider extends ServiceProvider
{
    private const PACKAGE = 'chikolokoy08/laravel-ph-toolkit';

    /**
     * The string rules, mapped to the core check behind each one and the
     * translation line used when it fails.
     *
     * @var array<string, array{0: callable(?string): bool, 1: string}>
     */
    private const STRING_RULES = [
        'ph_mobile' => [[Mobile::class, 'isValidMobileNumber'], 'mobile'],
        'ph_tin' => [[Tin::class, 'isValidTin'], 'tin'],
        'ph_zip' => [[Zip::class, 'isValidZipCode'], 'zip'],
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/ph-toolkit.php', 'ph-toolkit');

        $this->app->singleton(PhToolkit::class, static fn (): PhToolkit => new PhToolkit());
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'ph-toolkit');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ph-toolkit');

        Blade::component('ph-address-selector', AddressSelector::class);

        $this->registerStringRules();
        $this->registerAddressRoutes();
        $this->registerAboutEntry();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/ph-toolkit.php' => $this->app->configPath('ph-toolkit.php'),
            ], 'ph-toolkit-config');

            $this->publishes([
                __DIR__.'/../../lang' => $this->app->langPath('vendor/ph-toolkit'),
            ], 'ph-toolkit-translations');

            $this->publishes([
                __DIR__.'/../../resources/views' => $this->app->resourcePath('views/vendor/ph-toolkit'),
            ], 'ph-toolkit-views');
        }
    }

    /**
     * The rule objects carry their own messages. These string aliases have to
     * put one somewhere the validator will find, and fallbackMessages is the
     * slot Laravel consults last, after inline messages and
     * validation.custom.*, so an application message still wins. Setting it
     * when the rule fails rather than when it is registered keeps the message
     * on whatever locale is active for that request.
     */
    private function registerStringRules(): void
    {
        $this->callAfterResolving('validator', static function (ValidationFactory $factory): void {
            foreach (self::STRING_RULES as $rule => [$check, $line]) {
                $factory->extend(
                    $rule,
                    static function (
                        string $attribute,
                        mixed $value,
                        array $parameters,
                        ValidatorContract $validator,
                    ) use ($check, $rule, $line): bool {
                        if (is_string($value) && $check($value)) {
                            return true;
                        }

                        if ($validator instanceof Validator) {
                            $validator->fallbackMessages[$rule] = (string) trans('ph-toolkit::validation.'.$line);
                        }

                        return false;
                    },
                );
            }
        });
    }

    /**
     * Off unless asked for. The endpoints only read bundled data, but a site
     * that does not use the address selector has no reason to serve them.
     */
    private function registerAddressRoutes(): void
    {
        if (config('ph-toolkit.routes.enabled') !== true) {
            return;
        }

        $middleware = config('ph-toolkit.routes.middleware');

        Route::group([
            'prefix' => self::routePrefix(),
            'middleware' => is_array($middleware) ? $middleware : ['web'],
            'as' => 'ph-toolkit.',
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../../routes/address.php');
        });
    }

    private static function routePrefix(): string
    {
        $prefix = config('ph-toolkit.routes.prefix');

        return trim(is_string($prefix) ? $prefix : 'ph-toolkit', '/');
    }

    private function registerAboutEntry(): void
    {
        if (! class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('PH Toolkit', fn (): array => [
            'Version' => self::packageVersion(),
            'PSGC Version' => Address::psgcVersion(),
            'Address Routes' => config('ph-toolkit.routes.enabled') === true
                ? 'enabled at /'.self::routePrefix()
                : 'disabled',
        ]);
    }

    /**
     * Read from Composer rather than hardcoded, so it cannot drift from the
     * installed release.
     */
    private static function packageVersion(): string
    {
        if (! class_exists(InstalledVersions::class)) {
            return 'unknown';
        }

        if (! InstalledVersions::isInstalled(self::PACKAGE)) {
            return 'unknown';
        }

        return InstalledVersions::getPrettyVersion(self::PACKAGE) ?? 'unknown';
    }
}
