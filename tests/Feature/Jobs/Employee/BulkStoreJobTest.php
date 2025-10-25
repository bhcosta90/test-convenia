<?php

declare(strict_types=1);

use App\Jobs\Employee\BulkStoreJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // Use the real database queue so jobs are actually persisted (no fakes)
    config([
        'queue.default' => 'database',
    ]);
});

it('enqueues RegisterEmployeeJob instances in 50-sized chunks and deletes the file afterwards (integration, no fakes)', function (): void {
    // Build a CSV with comma delimiter and 120 non-empty rows + some blanks to be skipped
    // Note: BulkStoreJob detects delimiter based on the header content and will use ',' for the rows below
    $rows = [];
    $rows[] = 'name,email,cpf,city,state'; // header

    $total = 120;
    for ($i = 1; $i <= $total; $i++) {
        $rows[] = "Name $i,user$i@example.com,1234567890$i,City $i,ST";
        if ($i % 40 === 0) { // insert some blank lines that should be skipped
            $rows[] = ',,,,';
            $rows[] = '';
        }
    }

    $csv = implode("\n", $rows);

    $path = 'uploads/employees.csv';
    Storage::disk('local')->put($path, $csv);

    // Create a real batch and associate the job with it
    $batch = Bus::batch([])->name('employees-import')->dispatch();

    $job = new BulkStoreJob(userId: 123, file: $path);
    $job->withBatchId($batch->id);

    // Clear jobs table snapshot then run
    $before = (int) DB::table('jobs')->count();

    // Execute the job (this will enqueue child jobs into the database queue)
    $job->handle();

    $after = (int) DB::table('jobs')->count();

    // Expect: 120 RegisterEmployeeJob items were enqueued (blank lines ignored)
    expect($after - $before)->toBe($total);

    // File should be deleted after processing
    expect(Storage::disk('local')->exists($path))->toBeFalse();
});

it('does nothing when batch is cancelled and keeps the file (integration, no fakes)', function (): void {
    $csv = "name,email,cpf,city,state\nJohn,john@example.com,12345678901,City,ST";
    $path = 'uploads/employees.csv';
    Storage::disk('local')->put($path, $csv);

    // Create and cancel a real batch
    $batch = Bus::batch([])->name('employees-import-cancelled')->dispatch();
    $batch->cancel();

    $job = new BulkStoreJob(userId: 7, file: $path);
    $job->withBatchId($batch->id);

    $before = (int) DB::table('jobs')->count();

    $job->handle();

    // No jobs added and file kept (early return happens before delete)
    $after = (int) DB::table('jobs')->count();
    expect($after)->toBe($before)
        ->and(Storage::disk('local')->exists($path))->toBeTrue();
});

it('deletes the file on failed() (integration, no fakes)', function (): void {
    $csv = "name,email,cpf,city,state\nJohn,john@example.com,12345678901,City,ST";
    $path = 'uploads/employees.csv';
    Storage::disk('local')->put($path, $csv);

    $job = new BulkStoreJob(userId: 42, file: $path);
    // failed() does not require a batch
    $job->failed(new Exception('boom'));

    expect(Storage::disk('local')->exists($path))->toBeFalse();
});

it('handles missing or unreadable file gracefully (no jobs enqueued)', function (): void {
    // Point to a non-existent path; Storage::path will resolve but fopen will fail
    $path = 'uploads/does-not-exist.csv';

    // Real batch association
    $batch = Bus::batch([])->name('employees-import-missing-file')->dispatch();

    $job = new BulkStoreJob(userId: 55, file: $path);
    $job->withBatchId($batch->id);

    $before = (int) DB::table('jobs')->count();

    // Execute: the generator will early-return on failed fopen
    $job->handle();

    $after = (int) DB::table('jobs')->count();

    expect($after)->toBe($before)
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});

it('parses semicolon header branch and skips whitespace-only rows', function (): void {
    // Craft a header where one field contains a semicolon character inside quotes to trigger the
    // delimiter detection branch that selects ";"
    $rows = [];
    $rows[] = '"name;",email,cpf,city,state';
    $rows[] = 'John;john@example.com;11111111111;City;ST';
    $rows[] = ' ;  ;   ;   ;   '; // whitespace-only fields -> should be trimmed to empty and skipped
    $rows[] = 'Jane;jane@example.com;22222222222;Town;SP';
    $rows[] = ''; // empty line should also be skipped

    $csv = implode("\n", $rows);

    $path = 'uploads/employees-semicolon.csv';
    Storage::disk('local')->put($path, $csv);

    $batch = Bus::batch([])->name('employees-import-semicolon')->dispatch();

    $job = new BulkStoreJob(userId: 77, file: $path);
    $job->withBatchId($batch->id);

    $before = (int) DB::table('jobs')->count();
    $job->handle();
    $after = (int) DB::table('jobs')->count();

    // Two valid data lines enqueued, whitespace-only and empty lines skipped
    expect($after - $before)->toBe(2)
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});

it('gracefully handles unreadable file (permission denied on fopen) and deletes it', function (): void {
    $path = 'uploads/unreadable.csv';
    Storage::disk('local')->put($path, "col1,col2\nA,B\n");

    $realPath = Storage::path($path);
    // Remove all permissions so fopen fails but is_file() remains true
    @chmod($realPath, 0000);

    $batch = Bus::batch([])->name('employees-import-unreadable')->dispatch();

    $job = new BulkStoreJob(userId: 1, file: $path);
    $job->withBatchId($batch->id);

    $before = (int) DB::table('jobs')->count();

    try {
        $job->handle();
    } finally {
        // Restore permissions in case the file still exists for any reason
        @chmod($realPath, 0644);
    }

    $after = DB::table('jobs')->count();

    expect($after)->toBe($before)
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});
