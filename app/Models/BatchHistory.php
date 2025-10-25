<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class BatchHistory extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'batch_id',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'data' => 'array',
        ];
    }
}
