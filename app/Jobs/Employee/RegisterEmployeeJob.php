<?php

declare(strict_types=1);

namespace App\Jobs\Employee;

use App\Enums\BatchEnum;
use App\Http\Validation\EmployeeValidation;
use App\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RegisterEmployeeJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $userId,
        private readonly array $data
    ) {
        $this->delay(app()->isLocal() ? 0 : random_int(5, 120));
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $user = User::findOrFail($this->userId);

        [
            $name,
            $email,
            $cpf,
            $city,
            $state
        ] = $this->data;

        $validation = app(EmployeeValidation::class, [
            'user' => $user,
        ]);

        $employByEmail = $user->employees()->where('email', $email)->first();
        $employByCpf = $user->employees()->where('cpf', $cpf)->first();

        try {
            if ($employByEmail && $employByCpf && $employByEmail?->id !== $employByCpf?->id) {
                throw ValidationException::withMessages([
                    'email_or_cpf' => ['CPF and E-mail conflict for different employees.'],
                ]);
            }

            $rules = $validation->make($employByEmail ?: $employByCpf);
            $data = Validator::make(['name' => $name, 'email' => $email, 'cpf' => $cpf, 'city' => $city, 'state' => $state], $rules)
                ->validate();

            $user->employees()->updateOrCreate(['cpf' => only_numbers($cpf), 'email' => $email], $data);
        } catch (ValidationException $e) {
            $user = User::findOrFail($this->userId);
            $user->batch()->create([
                'type' => BatchEnum::EMPLOYEE_BULK_STORE,
                'batch_id' => $this->batch()->id,
                'data' => [
                    'errors' => $e->errors(),
                    'data' => $this->data,
                ],
            ]);
        }
    }
}
