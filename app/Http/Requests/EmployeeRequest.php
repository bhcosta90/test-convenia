<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EmployeeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'email' => ['required', 'email', 'max:254'],
            'cpf' => ['required'],
            'city' => ['required'],
            'state' => ['required'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
