<?php

namespace Database\Factories;

use App\Enums\RemoteType;
use App\Models\JobPosting;
use App\Models\JobSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();
        $company = fake()->company();
        $description = fake()->text(800);

        return [
            'job_source_id' => JobSource::factory(),
            'external_id' => (string) fake()->unique()->numberBetween(1, 9_999_999),
            'fingerprint' => JobPosting::fingerprintFor($company, $title, 'Remote'),
            'title' => $title,
            'company' => $company,
            'location' => 'Remote',
            'remote_type' => RemoteType::Remote,
            'description' => $description,
            'description_hash' => hash('sha256', $title."\n".$description),
            'url' => fake()->url(),
            'posted_at' => now()->subDays(2),
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now(),
        ];
    }
}
