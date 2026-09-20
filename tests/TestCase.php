<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Tests;

use Chikolokoy08\PhToolkit\Laravel\Facades\PhToolkit;
use Chikolokoy08\PhToolkit\Laravel\PhToolkitServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @var array<string, mixed> */
    private array $configOverrides = [];

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PhToolkitServiceProvider::class];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['PhToolkit' => PhToolkit::class];
    }

    protected function defineEnvironment($app): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make('config');

        foreach ($this->configOverrides as $key => $value) {
            $config->set($key, $value);
        }
    }

    /**
     * Sets configuration and rebuilds the application, so the provider boots
     * against it. Setting config on a booted application is too late for
     * anything decided at boot, such as whether the address routes exist.
     *
     * @param  array<string, mixed>  $values
     */
    protected function withPackageConfig(array $values): void
    {
        $this->configOverrides = [...$this->configOverrides, ...$values];

        $this->reloadApplication();
    }
}
