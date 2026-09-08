<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL and ADMIN_PASSWORD are not set; admin user was not created.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => env('ADMIN_NAME', 'CorpNotify Admin'), 'password' => $password, 'role' => 'admin'],
        );
    }
}
