<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;

it('boots a Laravel application', function (): void {
    expect($this->app)->toBeInstanceOf(Application::class);
});
