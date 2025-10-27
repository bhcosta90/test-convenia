<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Validation\EmployeeValidation;
use Illuminate\Foundation\Http\FormRequest;

final class EmployeeRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var EmployeeValidation $validation */
        $validation = app(EmployeeValidation::class);

        return $validation->make($this->route('employee'));
    }

    public function authorize(): bool
    {
        return true;
    }
}
