<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class EmployeePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool {}

    public function view(User $user, Employee $employee): bool {}

    public function create(User $user): bool {}

    public function update(User $user, Employee $employee): bool {}

    public function delete(User $user, Employee $employee): bool {}

    public function restore(User $user, Employee $employee): bool {}

    public function forceDelete(User $user, Employee $employee): bool {}
}
