<?php

namespace Tests\Feature\Http;

use App\Jobs\ParseResume;
use App\Jobs\ScoreMatches;
use App\Models\Resume;
use App\Models\ResumeSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_stores_the_file_privately_and_queues_parsing(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();
        $old = Resume::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('resume.store'), ['file' => UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf')])
            ->assertRedirect(route('resume.show'));

        $resume = $user->resumes()->latest('id')->firstOrFail();
        $this->assertTrue($resume->is_active);
        $this->assertFalse($old->fresh()?->is_active, 'only one active résumé');
        Storage::disk('local')->assertExists($resume->storage_path);
        Queue::assertPushed(ParseResume::class, fn (ParseResume $job) => $job->resumeId === $resume->id);
    }

    public function test_only_pdf_and_docx_are_accepted(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('resume.store'), ['file' => UploadedFile::fake()->create('cv.exe', 10, 'application/x-msdownload')])
            ->assertSessionHasErrors('file');
    }

    public function test_the_profile_page_shows_the_parsed_resume(): void
    {
        $user = User::factory()->create();
        $resume = Resume::factory()->for($user)->create();
        ResumeSection::factory()->for($resume)->withBullets(['Shipped things'])->create();

        $this->actingAs($user)->get(route('resume.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('resume/show')
                ->where('resume.id', $resume->id)
                ->has('resume.sections', 1)
                ->where('resume.sections.0.bullets.0.text', 'Shipped things'));
    }

    public function test_editing_a_section_keeps_bullet_ids_and_rescores(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $resume = Resume::factory()->for($user)->create();
        $section = ResumeSection::factory()->for($resume)->withBullets(['First', 'Second', 'Third'])->create();
        $firstId = $section->bullets()->first()?->id;

        $this->actingAs($user)->patch(route('resume.sections.update', $section), [
            'type' => 'experience',
            'title' => 'Lead Engineer',
            'is_current' => true,
            'bullets' => ['First (edited)', 'Second'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Lead Engineer', $section->fresh()?->title);
        $this->assertSame(['First (edited)', 'Second'], $section->bullets()->pluck('text')->all());
        $this->assertSame($firstId, $section->bullets()->first()?->id, 'edited lines keep their id');
        $this->assertSame(2, $resume->fresh()?->profile_version);
        Queue::assertPushed(ScoreMatches::class);
    }

    public function test_users_cannot_touch_someone_elses_resume(): void
    {
        $section = ResumeSection::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('resume.sections.update', $section), ['type' => 'experience'])
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->delete(route('resume.destroy', $section->resume))
            ->assertForbidden();
    }
}
