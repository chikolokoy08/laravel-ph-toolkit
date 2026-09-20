<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Laravel\Rules\PhMobileNumber;
use Chikolokoy08\PhToolkit\Laravel\Rules\PhTin;
use Chikolokoy08\PhToolkit\Laravel\Rules\PhZipCode;
use Illuminate\Support\Facades\Validator;

dataset('rule pairs', [
    'mobile' => [PhMobileNumber::class, 'ph_mobile', '0917 123 4567', '0917123', 'mobile number'],
    'tin' => [PhTin::class, 'ph_tin', '123-456-789', '12345678', 'TIN'],
    'zip' => [PhZipCode::class, 'ph_zip', '6000', '600', 'ZIP code'],
]);

it('passes a valid value', function (string $rule, string $stringRule, string $valid): void {
    expect(Validator::make(['field' => $valid], ['field' => [new $rule()]])->passes())->toBeTrue()
        ->and(Validator::make(['field' => $valid], ['field' => $stringRule])->passes())->toBeTrue();
})->with('rule pairs');

it('fails an invalid value', function (string $rule, string $stringRule, string $valid, string $invalid): void {
    expect(Validator::make(['field' => $invalid], ['field' => [new $rule()]])->fails())->toBeTrue()
        ->and(Validator::make(['field' => $invalid], ['field' => $stringRule])->fails())->toBeTrue();
})->with('rule pairs');

it('fails a value that is not a string', function (string $rule, string $stringRule): void {
    foreach ([42, [], true] as $value) {
        expect(Validator::make(['field' => $value], ['field' => [new $rule()]])->fails())->toBeTrue()
            ->and(Validator::make(['field' => $value], ['field' => $stringRule])->fails())->toBeTrue();
    }
})->with('rule pairs');

it('fails invalid UTF-8', function (string $rule, string $stringRule): void {
    $value = "0917\xFF1234567";

    expect(Validator::make(['field' => $value], ['field' => [new $rule()]])->fails())->toBeTrue()
        ->and(Validator::make(['field' => $value], ['field' => $stringRule])->fails())->toBeTrue();
})->with('rule pairs');

it('skips a null value when the field is nullable', function (string $rule, string $stringRule): void {
    expect(Validator::make(['field' => null], ['field' => ['nullable', new $rule()]])->passes())->toBeTrue()
        ->and(Validator::make(['field' => null], ['field' => ['nullable', $stringRule]])->passes())->toBeTrue();
})->with('rule pairs');

it('gives the object rule and the string rule the same message', function (string $rule, string $stringRule, string $valid, string $invalid): void {
    $object = Validator::make(['field' => $invalid], ['field' => [new $rule()]]);
    $string = Validator::make(['field' => $invalid], ['field' => $stringRule]);

    expect($object->errors()->first('field'))->toBe($string->errors()->first('field'));
})->with('rule pairs');

it('names the field in the message', function (string $rule, string $stringRule, string $valid, string $invalid, string $noun): void {
    $message = Validator::make(['mobile_number' => $invalid], ['mobile_number' => [new $rule()]])
        ->errors()->first('mobile_number');

    expect($message)->toContain('mobile number')
        ->and($message)->toContain($noun);
})->with('rule pairs');
