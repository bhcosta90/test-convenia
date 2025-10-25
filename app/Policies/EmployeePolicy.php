<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class EmployeePolicy
{
    use HandlesAuthorization;

    public function viewAny(): bool
    {
        return true;
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->id === $employee->user_id;
    }

    public function create(): bool
    {
        return true;
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->id === $employee->user_id;
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->id === $employee->user_id;
    }
}
