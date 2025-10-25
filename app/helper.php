<?php

declare(strict_types=1);

if (! function_exists('only_numbers')) {
    function only_numbers(?string $value): ?string
    {
        return when($value, preg_replace('/[^\d]/', '', (string) $value));
    }
}

if (! function_exists('format_cpf')) {
    function format_cpf(mixed $value): ?string
    {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', (string) $value);
    }
}
