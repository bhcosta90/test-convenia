<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'numeric', 'min:1'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
