<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BatchHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BatchHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'data' => $this->data,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
