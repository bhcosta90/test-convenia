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
            'name' => [new RequiredIf(! $id)],
            'email' => [new RequiredIf(! $id), 'email', 'max:254', Rule::unique(Employee::class)->where('user_id', $this->user()->id)->ignore($this->employee?->id)],
            'cpf' => [new RequiredIf(! $id), new CpfRule(), Rule::unique(Employee::class)->where('user_id', $this->user()->id)->ignore($this->employee?->id)],
            'city' => [new RequiredIf(! $id)],
            'state' => [new RequiredIf(! $id)],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
