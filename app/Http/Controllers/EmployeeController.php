<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class EmployeeController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Employee::class);

        return EmployeeResource::collection(Employee::all());
    }

    public function store(EmployeeRequest $request)
    {
        $this->authorize('create', Employee::class);

        return new EmployeeResource(Employee::create($request->validated()));
    }

    public function show(Employee $employee)
    {
        $this->authorize('view', $employee);

        return new EmployeeResource($employee);
    }

    public function update(EmployeeRequest $request, Employee $employee)
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
