<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Employee;
use App\Support\Cache\EmployeeListCache;

final class EmployeeObserver
{
    public function created(Employee $employee): void
    {
        EmployeeListCache::invalidateForUserId((int) $employee->user_id);
    }

    public function updated(Employee $employee): void
    {
        EmployeeListCache::invalidateForUserId((int) $employee->user_id);
    }

    public function deleted(Employee $employee): void
    {
        EmployeeListCache::invalidateForUserId((int) $employee->user_id);
    }
}
