<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Employee;
use App\Rules\CpfRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EmployeeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'email' => ['required', 'email', 'max:254', Rule::unique(Employee::class)->where('user_id', $this->user()->id)->ignore($this->employee?->id)],
            'cpf' => ['required', new CpfRule(), Rule::unique(Employee::class)->where('user_id', $this->user()->id)->ignore($this->employee?->id)],
            'city' => ['required'],
            'state' => ['required'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
