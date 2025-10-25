<?php

declare(strict_types=1);

namespace App\Jobs\Employee;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Throwable;

final class BulkStoreJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected readonly int $userId,
        protected readonly string $file
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $filePath = Storage::path($this->file);

        $lines = LazyCollection::make(function () use ($filePath) {
            $handle = fopen($filePath, 'r');
            if (! $handle) {
                return;
            }

            // lê header com escape
            $header = fgetcsv($handle, 0, ';', '"', '\\');
            $delimiter = str_contains(implode(',', $header), ';') ? ';' : ',';

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $row = array_map('trim', $row);
                if (empty(array_filter($row))) {
                    continue;
                }
                yield $row;
            }

            fclose($handle);
        });

        $lines->chunk(50)->each(function (LazyCollection $chunk) {
            $jobs = $chunk->map(fn ($line) => new RegisterEmployeeJob($this->userId, $line));
            $this->batch()->add($jobs); // sem ->all()
        });

        Storage::delete($this->file);
    }

    public function failed(Throwable $exception): void
    {
        Storage::delete($this->file);
    }
}
