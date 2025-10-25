<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Models\Employee;
use App\Models\User;
use App\Rules\CpfRule;
use App\Rules\CpfUniqueRule;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

final readonly class EmployeeValidation
{
    public function __construct(#[CurrentUser] private User $user)
    {
        //
    }

    public function make(?Employee $employee = null): array
    {
        $id = $employee?->id;

        return [
            'name' => [
                new RequiredIf(! $id),
                'string',
                'min:1',
            ],
            'email' => [
                new RequiredIf(! $id),
                'string',
                'min:1',
                'email:rfc,filter',
                'max:254',
                Rule::unique(Employee::class)->where('user_id', $this->user->id)->ignore($id),
            ],
            'cpf' => [
                new RequiredIf(! $id),
                'string',
                'min:1',
                new CpfRule(),
                new CpfUniqueRule($this->user, new Employee(), $id),
            ],
            'city' => [
                new RequiredIf(! $id),
                'string',
                'min:1',
            ],
            'state' => [
                new RequiredIf(! $id),
                'string',
                'min:1',
            ],
        ];
    }
}
