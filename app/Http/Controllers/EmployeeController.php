<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use App\Support\Cache\EmployeeListCache;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class EmployeeController
{
    use AuthorizesRequests;

    public function index(Requests\Employee\IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $page = (int) $request->query('page', 1);

        $user = $request->user();

        $payload = EmployeeListCache::remember($user->id, $page, function () use ($user) {
            $employees = $user->employees()
                ->orderBy('name')
                ->orderBy('created_at', 'desc')
                ->simplePaginate();

            return EmployeeResource::collection($employees)
                ->additional([
                    'meta' => [
                        'has_more_page' => $employees->hasMorePages(),
                    ],
                ])
                ->response()
                ->getData(true);
        });

        return response()->json($payload);
    }

    public function store(Requests\EmployeeRequest $request): EmployeeResource
    {
        $this->authorize('create', Employee::class);

        return new EmployeeResource($request->user()->employees()->create($request->validated()));
    }

    public function show(Employee $employee): EmployeeResource
    {
        $this->authorize('view', $employee);

        return new EmployeeResource($employee);
    }

    public function update(Requests\EmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $this->authorize('update', $employee);

        $employee->update($request->validated());

        return new EmployeeResource($employee);
    }

    public function destroy(Employee $employee): Response
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return response()->noContent();
    }
}
