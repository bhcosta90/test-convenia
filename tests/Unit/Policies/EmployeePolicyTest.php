<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\User;
use App\Policies\EmployeePolicy;

beforeEach(function (): void {
    $this->policy = new EmployeePolicy();

    $this->makeUser = function (int $id): User {
        $user = new User();
        // We don't need DB, just set the in-memory id
        $user->id = $id;

        return $user;
    };

    $this->makeEmployeeForUser = function (int $userId): Employee {
        $employee = new Employee();
        $employee->user_id = $userId;

        return $employee;
    };
});

// viewAny

test('viewAny always allows', function (): void {
    expect($this->policy->viewAny())->toBeTrue();
});

// create

test('create always allows', function (): void {
    expect($this->policy->create())->toBeTrue();
});

// view

test('view allows when user owns the employee', function (): void {
    $user = ($this->makeUser)(1);
    $employee = ($this->makeEmployeeForUser)(1);

    expect($this->policy->view($user, $employee))->toBeTrue();
});

test('view denies when user does not own the employee', function (): void {
    $user = ($this->makeUser)(1);
    $employee = ($this->makeEmployeeForUser)(2);

    expect($this->policy->view($user, $employee))->toBeFalse();
});

// update

test('update allows when user owns the employee', function (): void {
    $user = ($this->makeUser)(10);
    $employee = ($this->makeEmployeeForUser)(10);

    expect($this->policy->update($user, $employee))->toBeTrue();
});

test('update denies when user does not own the employee', function (): void {
    $user = ($this->makeUser)(10);
    $employee = ($this->makeEmployeeForUser)(20);

    expect($this->policy->update($user, $employee))->toBeFalse();
});

// delete

test('delete allows when user owns the employee', function (): void {
    $user = ($this->makeUser)(99);
    $employee = ($this->makeEmployeeForUser)(99);

    expect($this->policy->delete($user, $employee))->toBeTrue();
});

test('delete denies when user does not own the employee', function (): void {
    $user = ($this->makeUser)(99);
    $employee = ($this->makeEmployeeForUser)(100);

    expect($this->policy->delete($user, $employee))->toBeFalse();
});
