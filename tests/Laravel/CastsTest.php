<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Exceptions\InvalidPhValue;
use Chikolokoy08\PhToolkit\Tests\Support\Contact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('database.default', 'testing');

    Schema::create('contacts', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('mobile')->nullable();
        $table->string('tin')->nullable();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('contacts');
});

it('stores a mobile number as E.164 however it was written', function (string $input): void {
    $contact = Contact::create(['mobile' => $input]);

    expect($contact->mobile)->toBe('+639171234567')
        ->and($contact->getAttributes()['mobile'])->toBe('+639171234567');

    $contact->refresh();

    expect($contact->mobile)->toBe('+639171234567');
})->with([
    '09171234567',
    '0917 123 4567',
    '(0917) 123-4567',
    '+63 917 123 4567',
    '639171234567',
    '  0917.123.4567  ',
]);

it('stores a TIN in its formatted form however it was written', function (string $input, string $stored): void {
    $contact = Contact::create(['tin' => $input]);
    $contact->refresh();

    expect($contact->tin)->toBe($stored);
})->with([
    ['123456789', '123-456-789'],
    ['123 456 789', '123-456-789'],
    ['123456789000', '123-456-789-000'],
    ['12345678900000', '123-456-789-00000'],
    ['(123) 456-789', '123-456-789'],
]);

it('keeps null as null', function (): void {
    $contact = Contact::create(['mobile' => null, 'tin' => null]);
    $contact->refresh();

    expect($contact->mobile)->toBeNull()
        ->and($contact->tin)->toBeNull();
});

it('throws rather than saving an invalid mobile number', function (mixed $value): void {
    expect(fn (): Contact => Contact::create(['mobile' => $value]))
        ->toThrow(InvalidPhValue::class);
})->with([
    'too short' => ['0917123'],
    'landline' => ['0281234567'],
    'letters' => ['0917ABC4567'],
    'empty string' => [''],
    'unlisted 08 prefix' => ['08011234567'],
    'invalid UTF-8' => ["0917\xFF1234567"],
    'an integer' => [9171234567],
    'an array' => [[]],
]);

it('throws rather than saving an invalid TIN', function (mixed $value): void {
    expect(fn (): Contact => Contact::create(['tin' => $value]))
        ->toThrow(InvalidPhValue::class);
})->with([
    'eight digits' => ['12345678'],
    'ten digits' => ['1234567890'],
    'letters' => ['12345678A'],
    'empty string' => [''],
    'invalid UTF-8' => ["1234\xFF56789"],
    'an integer' => [123456789],
]);

it('writes nothing to the table when a value is rejected', function (): void {
    try {
        Contact::create(['mobile' => '0917123']);
    } catch (InvalidPhValue) {
        // The point of the test is what is left behind.
    }

    expect(Contact::count())->toBe(0);
});

it('explains what to do in the exception message', function (): void {
    expect(fn (): Contact => Contact::create(['mobile' => '0917123']))
        ->toThrow(InvalidPhValue::class, 'PhMobileNumber rule');

    expect(fn (): Contact => Contact::create(['tin' => '12345678']))
        ->toThrow(InvalidPhValue::class, 'PhTin rule');
});

it('does not put raw invalid bytes in the exception message', function (): void {
    try {
        Contact::create(['mobile' => "0917\xFF1234567"]);
    } catch (InvalidPhValue $exception) {
        expect($exception->getMessage())->toContain('not valid UTF-8')
            ->and(mb_check_encoding($exception->getMessage(), 'UTF-8'))->toBeTrue();

        return;
    }

    throw new RuntimeException('Expected the cast to reject invalid UTF-8.');
});

it('names the type when the value is not a string', function (): void {
    expect(fn (): Contact => Contact::create(['mobile' => 42]))
        ->toThrow(InvalidPhValue::class, 'a value of type int');
});
