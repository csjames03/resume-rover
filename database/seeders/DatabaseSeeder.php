<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SkillSeeder::class,
            JobSourceSeeder::class,
        ]);

        if (app()->isLocal()) {
            $user = User::query()->firstOrCreate(
                ['email' => 'test@example.com'],
                ['name' => 'Test User', 'password' => 'password', 'email_verified_at' => now()],
            );
            $user->forceFill(['is_admin' => true])->save();
        }
    }
}
