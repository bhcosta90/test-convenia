<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class EmployeeController
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Employee::class);

        return EmployeeResource::collection(Employee::all());
    }

    public function store(EmployeeRequest $request, #[CurrentUser] User $user): EmployeeResource
    {
        $this->authorize('create', Employee::class);

        return new EmployeeResource($user->employees()->create($request->validated()));
    }

    public function show(Employee $employee): EmployeeResource
    {
        $this->authorize('view', $employee);

        return new EmployeeResource($employee);
    }

    public function update(EmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $this->authorize('update', $employee);

        $employee->update($request->validated());

        return new EmployeeResource($employee);
    }

    public function destroy(Employee $employee)
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return response()->json();
    }
}
