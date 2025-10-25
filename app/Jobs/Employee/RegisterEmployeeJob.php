<?php

declare(strict_types=1);

namespace App\Jobs\Employee;

use App\Http\Validation\EmployeeValidation;
use App\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class RegisterEmployeeJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected readonly int $userId,
        protected readonly array $data
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $user = User::findOrFail($this->userId);

        [$name, $email, $cpf, $city, $state] = $this->data;

        $validation = app(EmployeeValidation::class, [
            'user' => $user,
        ]);

        $data = Validator::make(compact('name', 'email', 'cpf', 'city', 'state'), $validation->make())
            ->validate();
    }

    public function failed(Throwable $exception): void
    {
        dd($this->data);
    }
}
