<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee;

use App\Enums\BatchEnum;
use App\Http\Requests\Employee\BulkStoreRequest;
use App\Http\Resources\BatchHistoryResource;
use App\Jobs\Employee\BulkStoreJob;
use App\Notifications;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Bus;

final class BulkController
{
    public function index(#[CurrentUser] $user, string $id): AnonymousResourceCollection
    {
        return BatchHistoryResource::collection($user->batch()->where('batch_id', $id)->simplePaginate())
            ->additional([
                'batch' => Bus::findBatch($id),
            ]);
    }

    public function store(BulkStoreRequest $request): JsonResponse
    {
        $path = $request->file('file')->store('tmp');
        $request->user()->batch()->where('type', BatchEnum::EMPLOYEE_BULK_STORE)->delete();

        $batch = Bus::batch([
            new BulkStoreJob($request->user()->id, $path),
        ])
            ->then(fn () => $request->user()->notify(
                $request->user()->batch()->where('type', BatchEnum::EMPLOYEE_BULK_STORE)->count()
                    ? new Notifications\User\UploadFilePartialNotification()
                    : new Notifications\User\UploadFileSuccessNotification()
            ))
            ->dispatch();

        return response()->json([
            'message' => __('Bulk store send successfully'),
            'batch_id' => $batch->id,
        ]);
    }
}
