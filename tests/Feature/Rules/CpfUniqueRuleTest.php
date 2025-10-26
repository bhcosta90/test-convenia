<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\User;
use App\Rules\CpfUniqueRule;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    $this->validateCpf = function (User $user, string|Employee $table, mixed $cpf, ?int $ignoreId = null) {
        $rule = new CpfUniqueRule(user: $user, table: $table, ignoreId: $ignoreId);

        return Validator::make(
            ['cpf' => $cpf],
            ['cpf' => [$rule]]
        );
    };
});

it('passes when there is no existing record for the user and CPF (table as string)', function (): void {
    $user = User::factory()->create();

    $validator = ($this->validateCpf)($user, 'employees', '12345678901');
    expect($validator->passes())->toBeTrue();
});

it('fails when the same user has the same CPF (table as string)', function (): void {
    $user = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $user->id,
        'cpf' => '12345678901',
    ]);

    $validator = ($this->validateCpf)($user, 'employees', '12345678901');

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('cpf'))->toBeTrue()
        ->and($validator->errors()->first('cpf'))
        ->toBe(trans('validation.unique', ['attribute' => 'cpf']));
});

it('fails when the same user has the same CPF (table as Model instance)', function (): void {
    $user = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $user->id,
        'cpf' => '99988877766',
    ]);

    $validator = ($this->validateCpf)($user, new Employee(), '999.888.777-66'); // formatted input should be sanitized

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('cpf'))->toBeTrue();
});

it('passes when same CPF exists but for a different user', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Employee::factory()->create([
        'user_id' => $userB->id,
        'cpf' => '32165498700',
    ]);

    $validator = ($this->validateCpf)($userA, 'employees', '32165498700');

    expect($validator->passes())->toBeTrue();
});

it('passes when ignoring the current record id (update scenario)', function (): void {
    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'cpf' => '55544433322',
    ]);

    // ignore the very same record id -> should pass
    $validator = ($this->validateCpf)($user, 'employees', '55544433322', ignoreId: $employee->id);
    expect($validator->passes())->toBeTrue();
});

it('fails when another record with same CPF exists and ignoreId targets a different id', function (): void {
    $user = User::factory()->create();
    $employee1 = Employee::factory()->create([
        'user_id' => $user->id,
        'cpf' => '11122233344',
    ]);
    $employee2 = Employee::factory()->create([
        'user_id' => $user->id,
        'cpf' => '55566677788',
    ]);

    // Try to use ignoreId with a different record than the one that collides -> should still fail
    $validator = ($this->validateCpf)($user, 'employees', '11122233344', ignoreId: $employee2->id);

    expect($validator->fails())->toBeTrue();
});

it('passes when CPF is an empty string (early return; other rules should handle required)', function (): void {
    $user = User::factory()->create();

    $validator = ($this->validateCpf)($user, 'employees', '');
    expect($validator->passes())->toBeTrue();
});

it('passes when CPF is null (early return)', function (): void {
    $user = User::factory()->create();

    $validator = ($this->validateCpf)($user, 'employees', null);
    expect($validator->passes())->toBeTrue();
});

it('passes when CPF contains only non-digits (e.g., only punctuation/whitespace)', function (): void {
    $user = User::factory()->create();

    $validator = ($this->validateCpf)($user, 'employees', '..--  /  ');
    expect($validator->passes())->toBeTrue();
});

it('sanitizes formatted CPF and fails if a sanitized match exists', function (): void {
    $user = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $user->id,
        'cpf' => '01234567890',
    ]);

    // Input with dots and dash should match the stored digits-only value
    $validator = ($this->validateCpf)($user, 'employees', '012.345.678-90');

    expect($validator->fails())->toBeTrue();
});

it('sanitizes formatted CPF and passes if there is no existing match', function (): void {
    $user = User::factory()->create();

    $validator = ($this->validateCpf)($user, 'employees', '987.654.321-00');

    expect($validator->passes())->toBeTrue();
});
