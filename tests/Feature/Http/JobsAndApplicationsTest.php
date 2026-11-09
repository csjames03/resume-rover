<?php

namespace Tests\Feature\Http;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Enums\MatchCategory;
use App\Models\Application;
use App\Models\JobMatch;
use App\Models\JobPosting;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class JobsAndApplicationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_are_sorted_by_fit_with_unscored_last(): void
    {
        $user = User::factory()->create();
        [$low, $strong, $unscored] = JobPosting::factory()->count(3)->create();
        $this->match($user, $low, 30);
        $this->match($user, $strong, 90);

        $this->actingAs($user)->get(route('jobs.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('jobs/index')
                ->where('postings.data.0.id', $strong->id)
                ->where('postings.data.1.id', $low->id)
                ->where('postings.data.2.id', $unscored->id)
                ->where('postings.data.0.match.category', 'strong'));
    }

    public function test_jobs_can_be_filtered_by_category_and_closed_jobs_are_hidden(): void
    {
        $user = User::factory()->create();
        [$strong, $low] = JobPosting::factory()->count(2)->create();
        $closed = JobPosting::factory()->create(['closed_at' => now()]);
        $this->match($user, $strong, 85);
        $this->match($user, $low, 20);
        $this->match($user, $closed, 95);

        $this->actingAs($user)->get(route('jobs.index', ['category' => 'strong']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('postings.data', 1)
                ->where('postings.data.0.id', $strong->id));
    }

    public function test_another_users_matches_are_never_shown(): void
    {
        $posting = JobPosting::factory()->create();
        $this->match(User::factory()->create(), $posting, 90);

        $this->actingAs(User::factory()->create())->get(route('jobs.show', $posting))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('jobs/show')
                ->where('posting.match', null));
    }

    public function test_saving_and_progressing_an_application_records_history(): void
    {
        $user = User::factory()->create();
        $posting = JobPosting::factory()->create();

        $this->actingAs($user)->post(route('jobs.application.store', $posting))->assertRedirect();
        $application = Application::query()->sole();

        $this->actingAs($user)
            ->patch(route('applications.update', $application), ['status' => 'applied', 'notes' => 'Sent via site'])
            ->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertNotNull($application->applied_at);
        $this->assertEquals(
            [ApplicationEventType::Created, ApplicationEventType::StatusChanged, ApplicationEventType::NoteUpdated],
            $application->events()->reorder('id')->pluck('type')->all(),
        );
    }

    public function test_the_applications_page_remembers_the_board_view(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('applications.index', ['view' => 'board']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('view', 'board'));

        // Anything unexpected falls back to the table.
        $this->actingAs($user)->get(route('applications.index', ['view' => 'gallery']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('view', 'table'));
    }

    public function test_dropping_a_card_on_another_column_changes_only_the_status(): void
    {
        $user = User::factory()->create();
        $application = Application::query()->create([
            'user_id' => $user->id,
            'job_posting_id' => JobPosting::factory()->create()->id,
            'status' => ApplicationStatus::Applied,
            'notes' => 'Keep me',
        ]);

        $this->actingAs($user)
            ->patch(route('applications.update', $application), ['status' => 'interviewing'])
            ->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(ApplicationStatus::Interviewing, $application->status);
        $this->assertSame('Keep me', $application->notes);
        $this->assertSame(ApplicationEventType::StatusChanged, $application->events()->latest('id')->value('type'));
    }

    public function test_users_cannot_update_someone_elses_application(): void
    {
        $application = Application::query()->create([
            'user_id' => User::factory()->create()->id,
            'job_posting_id' => JobPosting::factory()->create()->id,
            'status' => ApplicationStatus::Saved,
        ]);

        $this->actingAs(User::factory()->create())
            ->patch(route('applications.update', $application), ['status' => 'offer'])
            ->assertForbidden();
    }

    public function test_every_page_renders(): void
    {
        $user = User::factory()->create();
        Resume::factory()->for($user)->create();
        $posting = JobPosting::factory()->create();
        $this->match($user, $posting, 70);

        $pages = [
            'dashboard' => route('dashboard'),
            'jobs/index' => route('jobs.index'),
            'jobs/show' => route('jobs.show', $posting),
            'applications/index' => route('applications.index'),
            'resume/show' => route('resume.show'),
            'sources/index' => route('sources.index'),
            'settings/preferences' => route('preferences.edit'),
            'settings/ai-providers' => route('ai-providers.show'),
        ];

        foreach ($pages as $component => $url) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component($component));
        }
    }

    private function match(User $user, JobPosting $posting, int $score): void
    {
        JobMatch::query()->create([
            'user_id' => $user->id,
            'job_posting_id' => $posting->id,
            'resume_id' => Resume::factory()->for($user)->create(['is_active' => false])->id,
            'resume_profile_version' => 1,
            'score' => $score,
            'category' => MatchCategory::fromScore($score),
            'strengths' => [],
            'gaps' => [],
            'scoring_version' => 'test',
            'scored_at' => now(),
        ]);
    }
}
