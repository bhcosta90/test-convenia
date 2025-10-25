<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

use function Laravel\Prompts\text;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = text('Enter user email: ');
        $name = text('Enter user name: ');
        $password = text('Enter user password (default is "password"): ', default: 'password');

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
    }
}
