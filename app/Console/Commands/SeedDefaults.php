<?php

namespace App\Console\Commands;

use App\Models\Skill;
use Database\Seeders\JobSourceSeeder;
use Database\Seeders\SkillSeeder;
use Illuminate\Console\Command;

/**
 * Loads the skills dictionary and starter job sources on a fresh database.
 * Runs on every container boot, so it does nothing once they exist. Unlike
 * `db:seed`, it never creates the local test account.
 */
class SeedDefaults extends Command
{
    protected $signature = 'app:seed-defaults {--force : Re-sync even if data already exists}';

    protected $description = 'Seed the skills dictionary and starter job sources on a fresh database';

    public function handle(): int
    {
        if (Skill::query()->exists() && ! $this->option('force')) {
            $this->info('Defaults already present.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => SkillSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => JobSourceSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
