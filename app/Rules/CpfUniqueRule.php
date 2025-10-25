<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class CpfUniqueRule implements ValidationRule
{
    public function __construct(
        private string|Model $table,
        private readonly ?int $ignoreId = null, // opcional para updates
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->table instanceof Model) {
            $this->table = $this->table->getTable();
        }

        // Limpa o CPF para conter apenas números
        $cpf = preg_replace('/\D/', '', (string) $value);

        // CPF vazio não deve dar erro aqui, deixe para outras rules (ex: required)
        if (empty($cpf)) {
            return;
        }

        // Faz a query no banco
        $query = DB::table($this->table)->where('cpf', $cpf);

        // Ignora um ID (em updates)
        if ($this->ignoreId) {
            $query->where('id', '!=', $this->ignoreId);
        }

        // Verifica se já existe
        if ($query->exists()) {
            $fail(__('validation.unique', ['attribute' => $attribute]));
        }
    }
}
