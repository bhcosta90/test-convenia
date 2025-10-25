<?php

declare(strict_types=1);

use App\Enums\BatchEnum;
use App\Jobs\Employee\RegisterEmployeeJob;
use App\Models\BatchHistory;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    // Helper: create a real batch and attach to the job
    $this->dispatchWithBatch = function (RegisterEmployeeJob $job) {
        $batch = Bus::batch([])->name('register-employee')->dispatch();
        $job->withBatchId($batch->id);

        return $job;
    };

    $this->validData = fn (array $overrides = []): array => array_values(array_merge([
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        // Valid CPF
        'cpf' => '52998224725',
        'city' => 'São Paulo',
        'state' => 'SP',
    ], $overrides));
});

it('creates a new employee when data is valid and there is no conflict', function (): void {
    $user = User::factory()->create();

    $job = new RegisterEmployeeJob(userId: $user->id, data: ($this->validData)());
    ($this->dispatchWithBatch)($job)->handle();

    $employee = Employee::where('user_id', $user->id)->first();

    expect($employee)->not->toBeNull()
        ->and($employee->name)->toBe('Alice Smith')
        ->and($employee->email)->toBe('alice@example.com')
        ->and($employee->cpf)->toBe('52998224725')
        ->and($employee->city)->toBe('São Paulo')
        ->and($employee->state)->toBe('SP')
        ->and(BatchHistory::count())->toBe(0);

});

it('updates an existing employee when email and cpf match the same record', function (): void {
    $user = User::factory()->create();

    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'name' => 'Old Name',
        'email' => 'alice@example.com',
        'cpf' => '52998224725',
        'city' => 'Old City',
        'state' => 'RJ',
    ]);

    $data = ($this->validData)([
        'name' => 'New Name',
        'city' => 'New City',
        'state' => 'MG',
    ]);

    $job = new RegisterEmployeeJob(userId: $user->id, data: $data);
    ($this->dispatchWithBatch)($job)->handle();

    $employee->refresh();

    expect($employee->name)->toBe('New Name')
        ->and($employee->city)->toBe('New City')
        ->and($employee->state)->toBe('MG')
        ->and(BatchHistory::count())->toBe(0);
});

it('returns early and does nothing when the batch is cancelled', function (): void {
    $user = User::factory()->create();

    $batch = Bus::batch([])->name('cancelled')->dispatch();
    $batch->cancel();

    $job = new RegisterEmployeeJob(userId: $user->id, data: ($this->validData)());
    $job->withBatchId($batch->id);

    $job->handle();

    expect(Employee::where('user_id', $user->id)->count())->toBe(0)
        ->and(BatchHistory::count())->toBe(0);
});

it('records batch history when email and cpf point to different employees (conflict)', function (): void {
    $user = User::factory()->create();

    // Existing employee with matching email
    $e1 = Employee::factory()->create([
        'user_id' => $user->id,
        'name' => 'Emp 1',
        'email' => 'alice@example.com',
        'cpf' => '12345678901',
        'city' => 'City1',
        'state' => 'SP',
    ]);

    // Existing employee with matching CPF
    $e2 = Employee::factory()->create([
        'user_id' => $user->id,
        'name' => 'Emp 2',
        'email' => 'other@example.com',
        'cpf' => '52998224725',
        'city' => 'City2',
        'state' => 'RJ',
    ]);

    $payload = ($this->validData)();

    $job = new RegisterEmployeeJob(userId: $user->id, data: $payload);
    ($this->dispatchWithBatch)($job)->handle();

    // Should not modify existing employees
    expect($e1->fresh()->name)->toBe('Emp 1')
        ->and($e2->fresh()->name)->toBe('Emp 2');

    // A BatchHistory record should be created with proper data
    $history = BatchHistory::first();
    expect($history)->not->toBeNull()
        ->and($history->type)->toBe(BatchEnum::EMPLOYEE_BULK_STORE)
        ->and($history->batch_id)->toBe($job->batch()->id)
        ->and($history->data)->toHaveKeys(['errors', 'data'])
        ->and($history->data['data'])->toBe($payload)
        ->and($history->data['errors'])->toBeArray();
});

it('records batch history when validation fails (invalid cpf) and does not create employee', function (): void {
    $user = User::factory()->create();

    $payload = ($this->validData)([
        // Invalid CPF triggers ValidationException from rules
        'cpf' => '11111111111',
    ]);

    $job = new RegisterEmployeeJob(userId: $user->id, data: $payload);
    ($this->dispatchWithBatch)($job)->handle();

    expect(Employee::where('user_id', $user->id)->count())->toBe(0);

    $history = BatchHistory::first();
    expect($history)->not->toBeNull()
        ->and($history->type)->toBe(BatchEnum::EMPLOYEE_BULK_STORE)
        ->and($history->batch_id)->toBe($job->batch()->id)
        ->and($history->data)->toHaveKeys(['errors', 'data'])
        ->and($history->data['data'])->toBe($payload)
        ->and(array_keys($history->data['errors']))->toContain('cpf');
});
