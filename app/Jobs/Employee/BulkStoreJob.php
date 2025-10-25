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

    public function __construct(protected readonly string $file) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $lines = LazyCollection::make(function () {
            $handle = fopen(Storage::path($this->file), 'r');
            if (! $handle) {
                return;
            }

            $firstLine = fgets($handle);
            if (! $firstLine) {
                return;
            }

            // Detecta delimitador
            $delimiter = str_contains($firstLine, ';') ? ';' : ',';

            // Retorna a primeira linha processada
            yield str_getcsv($firstLine, $delimiter, '"', '\\');

            while (($line = fgets($handle)) !== false) {
                $line = mb_trim($line);
                if ($line === '') {
                    continue;
                }

                yield str_getcsv($line, $delimiter, '"', '\\');
            }

            fclose($handle);
        });

        $lines->chunk(50)->each(function (LazyCollection $chunk) {
            $jobs = $chunk->map(fn ($line) => new RegisterEmployeeJob($line))->all();
            $this->batch()->add($jobs);
        });

        Storage::delete($this->file);
    }

    public function failed(Throwable $exception): void
    {
        Storage::delete($this->file);
    }
}
