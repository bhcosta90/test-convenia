<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Employee;
use App\Rules\CpfRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

final class EmployeeRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('employee');

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
                Rule::unique(Employee::class)->where('user_id', $this->user()->id)->ignore($this->employee?->id),
            ],
            'cpf' => [
                new RequiredIf(! $id),
                'string',
                'min:1',
                new CpfRule(),
                Rule::unique(Employee::class)->where('user_id', $this->user()->id)->ignore($this->employee?->id),
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

    public function authorize(): bool
    {
        return true;
    }
}
