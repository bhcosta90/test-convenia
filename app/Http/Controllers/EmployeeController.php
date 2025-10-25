<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Resources\BatchHistoryResource;
use App\Http\Resources\EmployeeResource;
use App\Jobs\Employee\BulkStoreJob;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Bus;

final class EmployeeController
{
    use AuthorizesRequests;

    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Employee::class);

        return EmployeeResource::collection($user->employees()->simplePaginate());
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

    public function destroy(Employee $employee): JsonResponse
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return response()->json();
    }

    public function bulkStore(Requests\Employee\BulkStoreRequest $request): JsonResponse
    {
        $path = $request->file('file')->store('tmp');
        $request->user()->batch()->delete();

        $batch = Bus::batch([
            new BulkStoreJob($request->user()->id, $path),
        ])
            ->dispatch();

        return response()->json([
            'message' => __('Bulk store send successfully'),
            'batch_id' => $batch->id,
        ]);
    }

    public function bulkHistory(#[CurrentUser] $user, string $id): AnonymousResourceCollection
    {
        return BatchHistoryResource::collection($user->batch()->where('batch_id', $id)->simplePaginate())
            ->additional([
                'batch' => Bus::findBatch($id),
            ]);
    }
}
