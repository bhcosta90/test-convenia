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
use Illuminate\Validation\ValidationException;

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

        $employByEmail = $user->employees()->where('email', $email)->first();
        $employByCpf = $user->employees()->where('cpf', $cpf)->first();

        try {
            if ($employByEmail?->id !== $employByCpf?->id) {
                throw ValidationException::withMessages([
                    'email_or_cpf' => ['CPF and E-mail conflict for different employees.'],
                ]);
            }

            $data = Validator::make(compact('name', 'email', 'cpf', 'city', 'state'), $validation->make($employByEmail?->id ?: $employByCpf?->id))
                ->validate();

            $user->employees()->updateOrCreate(compact('cpf', 'email'), $data);
        } catch (ValidationException $e) {
            $user = User::findOrFail($this->userId);
            $user->batch()->create([
                'batch_id' => $this->batch()->id,
                'data' => [
                    'error' => $e->errors(),
                    'data' => $this->data,
                ],
            ]);
        }
    }
}
