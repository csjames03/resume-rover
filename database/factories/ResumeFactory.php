<?php

namespace Database\Factories;

use App\Enums\ResumeParseStatus;
use App\Models\Resume;
use App\Models\User;
use App\Services\Resumes\ResumeTextExtractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resume>
 */
class ResumeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_filename' => 'resume.pdf',
            'storage_path' => 'resumes/test/'.fake()->uuid().'.pdf',
            'mime_type' => ResumeTextExtractor::PDF,
            'file_size' => 120_000,
            'file_hash' => hash('sha256', fake()->uuid()),
            'parse_status' => ResumeParseStatus::Parsed,
            'parser' => 'heuristic',
            'parsed_at' => now(),
            'raw_text' => fake()->paragraphs(3, true),
            'is_active' => true,
        ];
    }

    public function pending(): static
    {
        return $this->state(['parse_status' => ResumeParseStatus::Pending, 'parsed_at' => null, 'raw_text' => null]);
    }
}
