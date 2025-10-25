<?php

declare(strict_types=1);

use App\Rules\CpfRule;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->rule = new CpfRule();
});

function makeValidator(mixed $value)
{
    return Validator::make([
        'cpf' => $value,
    ], [
        'cpf' => [new CpfRule()],
    ]);
}

function makeNullableValidator(mixed $value)
{
    return Validator::make([
        'cpf' => $value,
    ], [
        'cpf' => ['nullable', new CpfRule()],
    ]);
}

test('valid CPF (unformatted) passes', function () {
    $validator = makeValidator('52998224725');

    expect($validator->passes())->toBeTrue();
});

test('valid CPF (formatted) passes', function () {
    $validator = makeValidator('529.982.247-25');

    expect($validator->passes())->toBeTrue();
});

test('CPF with invalid length (too short) fails', function () {
    $validator = makeValidator('1234567890'); // 10 digits

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('cpf'))
        ->toBe(__('The :attribute must be a valid CPF.', ['attribute' => 'cpf']));
});

test('CPF with invalid length (too long) fails', function () {
    $validator = makeValidator('123456789012'); // 12 digits

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('cpf'))
        ->toBe(__('The :attribute must be a valid CPF.', ['attribute' => 'cpf']));
});

test('CPF with all repeated digits fails', function () {
    foreach (['00000000000', '11111111111', '22222222222', '33333333333', '44444444444', '55555555555', '66666666666', '77777777777', '88888888888', '99999999999'] as $cpf) {
        $validator = makeValidator($cpf);
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->first('cpf'))
            ->toBe(__('The :attribute must be a valid CPF.', ['attribute' => 'cpf']));
    }
});

test('CPF with invalid check digits fails', function () {
    // Start from a valid CPF and tamper with the last digit
    $validator1 = makeValidator('52998224726'); // last digit changed
    expect($validator1->fails())->toBeTrue()
        ->and($validator1->errors()->first('cpf'))
        ->toBe(__('The :attribute must be a valid CPF.', ['attribute' => 'cpf']));

    // Tamper second-to-last digit as well
    $validator2 = makeValidator('52998224715');
    expect($validator2->fails())->toBeTrue()
        ->and($validator2->errors()->first('cpf'))
        ->toBe(__('The :attribute must be a valid CPF.', ['attribute' => 'cpf']));
});

test('null value passes when nullable', function () {
    $validator = makeNullableValidator(null);

    expect($validator->passes())->toBeTrue();
});

test('empty string passes when nullable', function () {
    $validator = makeNullableValidator('');

    expect($validator->passes())->toBeTrue();
});

test('empty string without nullable currently passes (documenting behavior)', function () {
    // Current rule returns early for empty string, allowing it to pass even without nullable
    $validator = makeValidator('');

    expect($validator->passes())->toBeTrue();
});

test('null without nullable currently passes (documenting behavior)', function () {
    // Current rule returns early for null as well, allowing it to pass without the nullable rule
    $validator = makeValidator(null);

    expect($validator->passes())->toBeTrue();
});

test('integer input is accepted when digits form a valid CPF', function () {
    // Same as 52998224725 but as integer (leading zeros would be lost in other cases)
    $validator = makeValidator(52998224725);

    expect($validator->passes())->toBeTrue();
});

test('value with leading and trailing whitespace passes when valid', function () {
    $validator = makeValidator('  529.982.247-25  ');

    expect($validator->passes())->toBeTrue();
});

test('random non-digit characters inside are ignored and valid digits still pass', function () {
    $validator = makeValidator('52a998b2247-25'); // letters will be stripped

    expect($validator->passes())->toBeTrue();
});

test('completely non-numeric value fails', function () {
    $validator = makeValidator('abcdefghijk');

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('cpf'))
        ->toBe(__('The :attribute must be a valid CPF.', ['attribute' => 'cpf']));
});

test('array value fails', function () {
    $validator = makeValidator(['52998224725']);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('cpf'))
        ->toBe(__('The :attribute must be a valid CPF.', ['attribute' => 'cpf']));
});
