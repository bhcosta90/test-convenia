<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\User;

beforeEach(function (): void {
    $this->validEmployeePayload = (fn (array $overrides = []): array => array_merge([
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'cpf' => '52998224725', // valid CPF from CpfRule tests
        'city' => 'São Paulo',
        'state' => 'SP',
    ], $overrides));
});

// AUTHENTICATION (401) for all endpoints
it('requires authentication for all employee endpoints', function (): void {
    $user = User::factory()->create();
    $employee = Employee::factory()->for($user)->create();

    // index
    $this->getJson('/api/employees')->assertStatus(401);
    // store
    $this->postJson('/api/employees', ($this->validEmployeePayload)())->assertStatus(401);
    // show
    $this->getJson("/api/employees/{$employee->id}")->assertStatus(401);
    // update
    $this->putJson("/api/employees/{$employee->id}", ['city' => 'Rio'])
        ->assertStatus(401);
    // destroy
    $this->deleteJson("/api/employees/{$employee->id}")
        ->assertStatus(401);
});

// INDEX
it('lists only authenticated user employees with pagination structure', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    // employees for user
    $mine = Employee::factory()->for($user)->count(3)->create();
    // employees for other user
    Employee::factory()->for($other)->count(2)->create();

    $this->actingAs($user, 'api');

    $response = $this->getJson('/api/employees')->assertOk();

    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'name', 'email', 'cpf', 'city', 'state', 'created_at', 'updated_at'],
        ],
    ]);

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->sort()->values()->all())
        ->toEqual($mine->pluck('id')->sort()->values()->all());
});

// STORE
it('creates an employee for the authenticated user and returns the resource JSON', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');

    $payload = ($this->validEmployeePayload)();

    $response = $this->postJson('/api/employees', $payload)->assertCreated();

    $response->assertJsonFragment([
        'name' => $payload['name'],
        'email' => $payload['email'],
        'cpf' => format_cpf($payload['cpf']),
        'city' => $payload['city'],
        'state' => $payload['state'],
    ]);

    $this->assertDatabaseHas('employees', [
        'user_id' => $user->id,
        'email' => $payload['email'],
        'cpf' => $payload['cpf'],
    ]);
});

it('validates required fields on store', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');

    $this->postJson('/api/employees', [])->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'cpf', 'city', 'state']);
});

it('validates email format and cpf rule on store', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');

    $payload = ($this->validEmployeePayload)([
        'email' => 'not-an-email',
        'cpf' => '12345678901',
    ]);

    $this->postJson('/api/employees', $payload)->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'cpf']);
});

it('enforces unique email and cpf per user on store but allows duplicates across users', function (): void {
    [$userA, $userB] = User::factory()->count(2)->create();

    Employee::factory()->for($userA)->create([
        'email' => 'dup@example.com',
        'cpf' => '52998224725',
    ]);

    // Same email/cpf for the same user -> 422
    $this->actingAs($userA, 'api');
    $this->postJson('/api/employees', ($this->validEmployeePayload)([
        'email' => 'dup@example.com',
        'cpf' => format_cpf('52998224725'),
    ]))->assertStatus(422)->assertJsonValidationErrors(['email', 'cpf']);

    // Same email/cpf for a different user -> OK
    $this->actingAs($userB, 'api');
    $this->postJson('/api/employees', ($this->validEmployeePayload)([
        'email' => 'dup@example.com',
        'cpf' => format_cpf('52998224725'),
    ]))->assertCreated();
});

// SHOW
it('shows a single employee for the owner', function (): void {
    $user = User::factory()->create();
    $employee = Employee::factory()->for($user)->create();

    $this->actingAs($user, 'api');

    $this->getJson("/api/employees/{$employee->id}")
        ->assertOk()
        ->assertJsonFragment([
            'id' => $employee->id,
            'email' => $employee->email,
            'cpf' => $employee->cpf,
        ]);
});

it('forbids access to employee of another user (show)', function (): void {
    [$owner, $intruder] = User::factory()->count(2)->create();
    $employee = Employee::factory()->for($owner)->create();

    $this->actingAs($intruder, 'api');

    $this->getJson("/api/employees/{$employee->id}")
        ->assertStatus(403);
});

// UPDATE
it('updates allowed fields for the owner and returns resource', function (): void {
    $user = User::factory()->create();
    $employee = Employee::factory()->for($user)->create([
        'city' => 'Old City',
        'state' => 'AC',
    ]);

    $this->actingAs($user, 'api');

    $payload = ['city' => 'New City']; // partial update allowed (required rules relaxed on update)

    $this->putJson("/api/employees/{$employee->id}", $payload)
        ->assertOk()
        ->assertJsonFragment([
            'id' => $employee->id,
            'city' => 'New City',
            'state' => 'AC',
        ]);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'city' => 'New City',
    ]);
});

it('validates format and uniqueness on update and ignores current record in unique rule', function (): void {
    $user = User::factory()->create();
    $mine = Employee::factory()->for($user)->create([
        'email' => 'me@example.com',
        'cpf' => '52998224725',
    ]);
    $otherMine = Employee::factory()->for($user)->create([
        'email' => 'other@example.com',
        'cpf' => '15350946056', // another valid CPF
    ]);

    $this->actingAs($user, 'api');

    // invalid email/cpf
    $this->putJson("/api/employees/{$mine->id}", [
        'email' => 'invalid-email',
        'cpf' => '12345678901',
    ])->assertStatus(422)->assertJsonValidationErrors(['email', 'cpf']);

    // unique conflict with other record of same user
    $this->putJson("/api/employees/{$mine->id}", [
        'email' => $otherMine->email,
        'cpf' => $otherMine->cpf,
    ])->assertStatus(422)->assertJsonValidationErrors(['email', 'cpf']);

    // can keep same values (unique ignore current id)
    $this->putJson("/api/employees/{$mine->id}", [
        'email' => 'me@example.com',
        'cpf' => '52998224725',
    ])->assertOk();
});

it('forbids update by non-owner', function (): void {
    [$owner, $intruder] = User::factory()->count(2)->create();
    $employee = Employee::factory()->for($owner)->create();

    $this->actingAs($intruder, 'api');

    $this->putJson("/api/employees/{$employee->id}", ['city' => 'X'])
        ->assertStatus(403);
});

// DESTROY
it('deletes (soft deletes) an employee for the owner and returns empty JSON', function (): void {
    $user = User::factory()->create();
    $employee = Employee::factory()->for($user)->create();

    $this->actingAs($user, 'api');

    $this->deleteJson("/api/employees/{$employee->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('employees', [
        'id' => $employee->id,
    ]);
});

it('forbids delete by non-owner', function (): void {
    [$owner, $intruder] = User::factory()->count(2)->create();
    $employee = Employee::factory()->for($owner)->create();

    $this->actingAs($intruder, 'api');

    $this->deleteJson("/api/employees/{$employee->id}")
        ->assertStatus(403);
});
