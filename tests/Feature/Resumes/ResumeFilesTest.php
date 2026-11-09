<?php

namespace Tests\Feature\Resumes;

use App\Models\Resume;
use App\Services\Resumes\ResumeFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResumeFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_files_in_a_bucket_are_copied_to_a_temp_path_and_cleaned_up(): void
    {
        config(['filesystems.resume_disk' => 's3']);
        Storage::fake('s3');
        Storage::disk('s3')->put('resumes/1/cv.pdf', '%PDF-1.4 remote');
        $resume = Resume::factory()->create(['storage_path' => 'resumes/1/cv.pdf']);

        $seenPath = app(ResumeFiles::class)->withLocalPath($resume, function (string $path) {
            $this->assertSame('%PDF-1.4 remote', file_get_contents($path));

            return $path;
        });

        $this->assertFileDoesNotExist($seenPath);
    }

    public function test_local_files_are_read_in_place(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('resumes/1/cv.pdf', '%PDF-1.4 local');
        $resume = Resume::factory()->create(['storage_path' => 'resumes/1/cv.pdf']);

        $path = app(ResumeFiles::class)->withLocalPath($resume, fn (string $path) => $path);

        $this->assertSame(Storage::disk('local')->path('resumes/1/cv.pdf'), $path);
        $this->assertFileExists($path);
    }
}
