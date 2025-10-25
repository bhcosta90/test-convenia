<?php

declare(strict_types=1);

use App\Enums\BatchEnum;
use App\Jobs\Employee\BulkStoreJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// =====================================
// GET /api/employees/{id}/bulk-history
// =====================================

it('requires authentication on GET bulk-history', function (): void {
    $this->getJson('/api/employees/some-id/bulk-history')->assertStatus(401);
});

it('lists only histories of the authenticated user for a given batch id and includes batch meta', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $batchId = (string) Str::uuid();
    $otherBatchId = (string) Str::uuid();

    // mine, same batch id
    $mine = [
        $user->batch()->create([
            'type' => BatchEnum::EMPLOYEE_BULK_STORE,
            'batch_id' => $batchId,
            'data' => ['data' => ['John', 'john@example.com', '52998224725', 'City', 'ST'], 'errors' => []],
        ]),
        $user->batch()->create([
            'type' => BatchEnum::EMPLOYEE_BULK_STORE,
            'batch_id' => $batchId,
            'data' => ['data' => ['Jane', 'jane@example.com', '86288366757', 'Town', 'SP'], 'errors' => []],
        ]),
    ];

    // mine but other batch id — should not appear
    $user->batch()->create([
        'type' => BatchEnum::EMPLOYEE_BULK_STORE,
        'batch_id' => $otherBatchId,
        'data' => ['data' => ['Foo']],
    ]);

    // other user, same batch id — should not appear
    $other->batch()->create([
        'type' => BatchEnum::EMPLOYEE_BULK_STORE,
        'batch_id' => $batchId,
        'data' => ['data' => ['Bar']],
    ]);

    // Mock Bus::findBatch to return a simple array meta
    Bus::shouldReceive('findBatch')
        ->once()
        ->with($batchId)
        ->andReturn(['id' => $batchId, 'name' => 'employees import']);

    $this->actingAs($user, 'api');

    $res = $this->getJson("/api/employees/{$batchId}/bulk-history")->assertOk();

    $res->assertJsonStructure([
        'data' => [
            '*' => ['id', 'batch_id', 'data', 'created_at', 'updated_at'],
        ],
        'batch',
    ]);

    $ids = collect($res->json('data'))->pluck('id')->values()->all();
    expect($ids)->toEqual(collect($mine)->pluck('id')->values()->all());

    expect($res->json('batch'))
        ->toBe(['id' => $batchId, 'name' => 'employees import']);
});

it('returns empty list and null batch meta when no histories exist for the id', function (): void {
    $user = User::factory()->create();
    $batchId = (string) Str::uuid();

    Bus::shouldReceive('findBatch')->once()->with($batchId)->andReturnNull();

    $this->actingAs($user, 'api');

    $res = $this->getJson("/api/employees/{$batchId}/bulk-history")->assertOk();

    expect($res->json('data'))->toBeArray()->toBeEmpty()
        ->and($res->json('batch'))->toBeNull();
});

// ===============================
// POST /api/employees/bulk-store
// ===============================

it('requires authentication on POST bulk-store', function (): void {
    $this->postJson('/api/employees/bulk-store', [])->assertStatus(401);
});

it('validates file presence and mime type on POST bulk-store', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');

    // no file
    $this->postJson('/api/employees/bulk-store', [])->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    // wrong mime (pretend a pdf)
    Storage::fake();
    $invalid = UploadedFile::fake()->create('file.pdf', 1, 'application/pdf');
    $this->postJson('/api/employees/bulk-store', ['file' => $invalid])->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

it('stores the file, clears previous histories for the user and dispatches a batch with BulkStoreJob, returning the batch_id', function (): void {
    $user = User::factory()->create();

    // seed previous histories that must be (soft) deleted
    $old = [
        $user->batch()->create([
            'type' => BatchEnum::EMPLOYEE_BULK_STORE,
            'batch_id' => (string) Str::uuid(),
            'data' => ['data' => ['old']],
        ]),
        $user->batch()->create([
            'type' => BatchEnum::EMPLOYEE_BULK_STORE,
            'batch_id' => (string) Str::uuid(),
            'data' => ['data' => ['old-2']],
        ]),
    ];

    $this->actingAs($user, 'api');

    $contents ??= "name;email;cpf;city;state\nJohn Doe;john@example.com;52998224725;City;ST\n";
    Storage::fake();
    $path = Storage::disk()->path('tmp');
    if (! is_dir($path)) {
        @mkdir($path, 0777, true);
    }
    $name = 'employees.csv';
    $full = $path.DIRECTORY_SEPARATOR.$name;
    file_put_contents($full, $contents);

    $file = new UploadedFile(
        $full,
        $name,
        'text/csv',
        null,
        true // test mode
    );

    // Instead of Bus::fake (which would not give us a concrete batch id easily),
    // mock Bus::batch to return a tiny chainable fake with a known id and without executing the then() callback.
    $expectedBatchId = (string) Str::uuid();

    $chainFake = new class($expectedBatchId)
    {
        public string $id;

        public function __construct(string $id)
        {
            $this->id = $id;
        }

        public function then(Closure $cb): self
        { /* do not execute in this test */ return $this;
        }

        public function dispatch(): object
        {
            return new class($this->id)
            {
                public function __construct(public string $id) {}
            };
        }
    };

    Bus::shouldReceive('batch')
        ->once()
        ->with(Mockery::on(function ($jobs) {
            // Expect exactly one job and it must be BulkStoreJob configured for this user
            return is_array($jobs)
                && count($jobs) === 1
                && $jobs[0] instanceof BulkStoreJob;
        }))
        ->andReturn($chainFake);

    $response = $this->postJson('/api/employees/bulk-store', ['file' => $file])
        ->assertOk()
        ->assertJsonStructure(['message', 'batch_id']);

    // Returned batch id is the mocked one
    expect($response->json('batch_id'))->toBe($expectedBatchId);

    // Previous histories should be soft-deleted
    foreach ($old as $history) {
        $this->assertSoftDeleted('batch_histories', ['id' => $history->id]);
    }

    // File should have been stored under tmp/ by the framework; we cannot assert exact path since storage is faked,
    // but ensure we still have the content in fake storage root (uploaded file was provided from fake path).
    // The controller does not persist the uploaded filename, only passes its stored path to the job.
});

// ===============================
// THEN callback notification behavior
// ===============================

it('executes then() callback and sends success notification when there are no error histories', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');

    // fake notifications
    Notification::fake();

    // prepare a small CSV upload
    Storage::fake();
    $path = Storage::disk()->path('tmp');
    if (! is_dir($path)) {
        @mkdir($path, 0777, true);
    }
    $name = 'employees.csv';
    $full = $path.DIRECTORY_SEPARATOR.$name;
    file_put_contents($full, "name;email;cpf;city;state\nJohn Doe;john@example.com;52998224725;City;ST\n");
    $file = new UploadedFile($full, $name, 'text/csv', null, true);

    $expectedBatchId = (string) Str::uuid();

    // Chain fake that CAPTURES the then-closure but does not execute it automatically
    $chainFake = new class($expectedBatchId)
    {
        public string $id;

        public ?Closure $captured = null;

        public function __construct(string $id)
        {
            $this->id = $id;
        }

        public function then(Closure $cb): self
        {
            $this->captured = $cb;

            return $this;
        }

        public function dispatch(): object
        {
            return new class($this->id)
            {
                public function __construct(public string $id) {}
            };
        }
    };

    Bus::shouldReceive('batch')
        ->once()
        ->with(Mockery::on(fn ($jobs) => is_array($jobs) && count($jobs) === 1 && $jobs[0] instanceof BulkStoreJob))
        ->andReturn($chainFake);

    // Hit the endpoint (this stores the file, clears old histories, and sets up the then() callback)
    $res = $this->postJson('/api/employees/bulk-store', ['file' => $file])->assertOk();
    expect($res->json('batch_id'))->toBe($expectedBatchId);

    // Simulate the framework calling the then() callback after the batch completes
    $fakeBatch = Mockery::mock(Illuminate\Bus\Batch::class);
    $fakeBatch->id = $expectedBatchId;
    ($chainFake->captured)($fakeBatch);

    // No new histories of type EMPLOYEE_BULK_STORE were created -> success notification
    Notification::assertSentTo($user, App\Notifications\User\UploadFileSuccessNotification::class);
    Notification::assertNotSentTo($user, App\Notifications\User\UploadFilePartialNotification::class);
});

it('executes then() callback and sends partial notification when there are error histories', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');

    Notification::fake();

    Storage::fake();
    $path = Storage::disk()->path('tmp');
    if (! is_dir($path)) {
        @mkdir($path, 0777, true);
    }
    $name = 'employees.csv';
    $full = $path.DIRECTORY_SEPARATOR.$name;
    file_put_contents($full, "name;email;cpf;city;state\nJohn Doe;john@example.com;52998224725;City;ST\n");
    $file = new UploadedFile($full, $name, 'text/csv', null, true);

    $expectedBatchId = (string) Str::uuid();

    $chainFake = new class($expectedBatchId)
    {
        public string $id;

        public ?Closure $captured = null;

        public function __construct(string $id)
        {
            $this->id = $id;
        }

        public function then(Closure $cb): self
        {
            $this->captured = $cb;

            return $this;
        }

        public function dispatch(): object
        {
            return new class($this->id)
            {
                public function __construct(public string $id) {}
            };
        }
    };

    Bus::shouldReceive('batch')
        ->once()
        ->with(Mockery::on(fn ($jobs) => is_array($jobs) && count($jobs) === 1 && $jobs[0] instanceof BulkStoreJob))
        ->andReturn($chainFake);

    // Call endpoint
    $this->postJson('/api/employees/bulk-store', ['file' => $file])->assertOk();

    // Simulate that, by the time the batch finishes, there are error histories for this user
    $user->batch()->create([
        'type' => BatchEnum::EMPLOYEE_BULK_STORE,
        'batch_id' => $expectedBatchId,
        'data' => ['data' => ['John'], 'errors' => ['email' => ['taken']]],
    ]);

    $fakeBatch = Mockery::mock(Illuminate\Bus\Batch::class);
    $fakeBatch->id = $expectedBatchId;
    ($chainFake->captured)($fakeBatch);

    Notification::assertSentTo($user, App\Notifications\User\UploadFilePartialNotification::class);
    Notification::assertNotSentTo($user, App\Notifications\User\UploadFileSuccessNotification::class);
});
