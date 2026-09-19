<?php

declare(strict_types=1);

it('has the intl extension the package requires', function (): void {
    expect(extension_loaded('intl'))->toBeTrue();
});
