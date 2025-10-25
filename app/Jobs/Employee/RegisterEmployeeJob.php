<?php

declare(strict_types=1);

namespace App\Jobs\Employee;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RegisterEmployeeJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected readonly array $data) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        logger($this->data);
    }
}
