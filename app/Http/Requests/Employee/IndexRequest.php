<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

final class IndexRequest extends FormRequest
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
