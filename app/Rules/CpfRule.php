<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class CpfRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Allow nullable fields to pass when combined with the `nullable` rule
        if ($value === null || $value === '') {
            return;
        }

        // Guard against non-stringable types (e.g., arrays, objects without __toString)
        if (! is_string($value) && ! is_int($value)) {
            $fail(__('The :attribute must be a valid CPF.'));

            return;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === null || mb_strlen($digits) !== 11) {
            $fail(__('The :attribute must be a valid CPF.'));

            return;
        }

        // Reject CPFs with all repeated digits (e.g., 00000000000, 11111111111, ...)
        if (preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            $fail(__('The :attribute must be a valid CPF.'));

            return;
        }

        if (! $this->validateCheckDigits($digits)) {
            $fail(__('The :attribute must be a valid CPF.'));
        }
    }

    private function validateCheckDigits(string $digits): bool
    {
        // Calculate first check digit
        $sum = 0;
        for ($i = 0, $weight = 10; $i < 9; $i++, $weight--) {
            $sum += ((int) $digits[$i]) * $weight;
        }
        $rest = $sum % 11;
        $d1 = ($rest < 2) ? 0 : 11 - $rest;

        // Calculate second check digit
        $sum = 0;
        for ($i = 0, $weight = 11; $i < 10; $i++, $weight--) {
            $sum += ((int) $digits[$i]) * $weight;
        }
        $rest = $sum % 11;
        $d2 = ($rest < 2) ? 0 : 11 - $rest;

        return $digits[9] === (string) $d1 && $digits[10] === (string) $d2;
    }
}
