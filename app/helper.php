<?php

declare(strict_types=1);

if (! function_exists('only_numbers')) {
    function only_numbers(?string $value): ?string
    {
        return when($value, preg_replace('/[^\d]/', '', $value));
    }
}
