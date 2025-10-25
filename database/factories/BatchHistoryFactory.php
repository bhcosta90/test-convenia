<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BatchHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class BatchHistoryFactory extends Factory
{
    protected $model = BatchHistory::class;

    public function definition(): array
    {
        return [
            'data' => $this->faker->words(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
