<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class RefreshRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
