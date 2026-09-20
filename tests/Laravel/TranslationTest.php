<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Laravel\Rules\PhMobileNumber;
use Chikolokoy08\PhToolkit\Laravel\Rules\PhTin;
use Chikolokoy08\PhToolkit\Laravel\Rules\PhZipCode;
use Illuminate\Support\Facades\Validator;

it('ships an English line for every rule', function (string $line, string $expected): void {
    app()->setLocale('en');

    expect(trans('ph-toolkit::validation.'.$line))->toBe($expected);
})->with([
    ['mobile', 'The :attribute field must be a valid Philippine mobile number.'],
    ['tin', 'The :attribute field must be a valid Philippine TIN.'],
    ['zip', 'The :attribute field must be a valid Philippine ZIP code.'],
]);

it('ships a Filipino line for every rule', function (string $line, string $expected): void {
    app()->setLocale('fil');

    expect(trans('ph-toolkit::validation.'.$line))->toBe($expected);
})->with([
    ['mobile', 'Dapat wastong numero ng mobile sa Pilipinas ang :attribute.'],
    ['tin', 'Dapat wastong TIN ang :attribute.'],
    ['zip', 'Dapat wastong ZIP code ang :attribute.'],
]);

it('covers exactly the same lines in both languages', function (): void {
    $en = require __DIR__.'/../../lang/en/validation.php';
    $fil = require __DIR__.'/../../lang/fil/validation.php';

    expect(array_keys($fil))->toBe(array_keys($en));
});

it('uses the Filipino message when the locale is fil', function (string $rule, string $invalid, string $expected): void {
    app()->setLocale('fil');

    $message = Validator::make(['numero' => $invalid], ['numero' => [new $rule()]])->errors()->first('numero');

    expect($message)->toBe($expected);
})->with([
    [PhMobileNumber::class, '0917123', 'Dapat wastong numero ng mobile sa Pilipinas ang numero.'],
    [PhTin::class, '12345678', 'Dapat wastong TIN ang numero.'],
    [PhZipCode::class, '600', 'Dapat wastong ZIP code ang numero.'],
]);

it('uses the Filipino message for the string rules too', function (string $stringRule, string $invalid, string $expected): void {
    app()->setLocale('fil');

    $message = Validator::make(['numero' => $invalid], ['numero' => $stringRule])->errors()->first('numero');

    expect($message)->toBe($expected);
})->with([
    ['ph_mobile', '0917123', 'Dapat wastong numero ng mobile sa Pilipinas ang numero.'],
    ['ph_tin', '12345678', 'Dapat wastong TIN ang numero.'],
    ['ph_zip', '600', 'Dapat wastong ZIP code ang numero.'],
]);

it('follows a locale change made after the provider booted', function (): void {
    app()->setLocale('en');
    $english = Validator::make(['field' => '0917123'], ['field' => 'ph_mobile'])->errors()->first('field');

    app()->setLocale('fil');
    $filipino = Validator::make(['field' => '0917123'], ['field' => 'ph_mobile'])->errors()->first('field');

    expect($english)->toContain('must be a valid Philippine mobile number')
        ->and($filipino)->toContain('Dapat wastong numero ng mobile');
});

it('lets an application message win over the packaged string rule', function (): void {
    $message = Validator::make(
        ['mobile' => '0917123'],
        ['mobile' => 'ph_mobile'],
        ['mobile.ph_mobile' => 'Pakisuri ang numero.'],
    )->errors()->first('mobile');

    expect($message)->toBe('Pakisuri ang numero.');
});

it('lets an application message win over the packaged rule object', function (string $key): void {
    // Laravel looks a rule object's message up by its class name and by the
    // snake case of its basename, so both spellings are worth covering.
    $message = Validator::make(
        ['mobile' => '0917123'],
        ['mobile' => [new PhMobileNumber()]],
        [$key => 'Pakisuri ang numero.'],
    )->errors()->first('mobile');

    expect($message)->toBe('Pakisuri ang numero.');
})->with([
    'snake case basename' => ['mobile.ph_mobile_number'],
    'full class name' => ['mobile.'.PhMobileNumber::class],
]);
