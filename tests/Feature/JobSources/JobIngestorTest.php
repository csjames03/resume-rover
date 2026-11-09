<?php

namespace Tests\Feature\JobSources;

use App\Enums\JobSourceDriver;
use App\Enums\JobSourceStatus;
use App\Models\JobPosting;
use App\Models\JobSource;
use App\Models\User;
use App\Services\JobSources\JobIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesSkills;
use Tests\TestCase;

class JobIngestorTest extends TestCase
{
    use CreatesSkills, RefreshDatabase;

    public function test_it_normalizes_postings_and_extracts_requirements(): void
    {
        $this->createSkills();
        $this->fakeGreenhouse([$this->greenhouseJob(1, 'Laravel Developer', 'Remote, Philippines')]);

        $result = app(JobIngestor::class)->ingest($source = $this->source());

        $this->assertSame(1, $result->created);
        $posting = JobPosting::query()->sole();
        $this->assertSame('Laravel Developer', $posting->title);
        $this->assertSame('GitLab', $posting->company);
        $this->assertSame('remote', $posting->remote_type->value);
        $this->assertStringNotContainsString('<p>', $posting->description, 'HTML is converted to text');
        $this->assertTrue($posting->requirements()->exists());
        $this->assertSame(JobSourceStatus::Ok, $source->fresh()?->last_status);
    }

    public function test_running_twice_creates_no_duplicates(): void
    {
        $this->fakeGreenhouse([$this->greenhouseJob(1, 'Laravel Developer'), $this->greenhouseJob(2, 'PHP Developer')]);
        $source = $this->source();

        app(JobIngestor::class)->ingest($source);
        $second = app(JobIngestor::class)->ingest($source);

        $this->assertSame(0, $second->created);
        $this->assertSame(0, $second->updated);
        $this->assertSame(2, JobPosting::query()->count());
    }

    public function test_postings_missing_from_the_feed_are_closed(): void
    {
        $source = $this->source();
        Http::fakeSequence('boards-api.greenhouse.io/*')
            ->push(['jobs' => [$this->greenhouseJob(1, 'A'), $this->greenhouseJob(2, 'B')]])
            ->push(['jobs' => [$this->greenhouseJob(1, 'A')]]);

        app(JobIngestor::class)->ingest($source);
        $this->travel(1)->minutes();
        $result = app(JobIngestor::class)->ingest($source);

        $this->assertSame(1, $result->closed);
        $this->assertNotNull(JobPosting::query()->where('external_id', '2')->value('closed_at'));
    }

    public function test_an_empty_feed_closes_nothing(): void
    {
        $source = $this->source();
        Http::fakeSequence('boards-api.greenhouse.io/*')
            ->push(['jobs' => [$this->greenhouseJob(1, 'A')]])
            ->push(['jobs' => []]);

        app(JobIngestor::class)->ingest($source);
        app(JobIngestor::class)->ingest($source);

        $this->assertNull(JobPosting::query()->value('closed_at'));
    }

    public function test_the_same_job_from_two_sources_is_kept_once(): void
    {
        $this->fakeGreenhouse([$this->greenhouseJob(1, 'Laravel Developer', 'Manila')]);
        Http::fake(['api.lever.co/*' => Http::response([[
            'id' => 'abc', 'text' => 'Laravel Developer', 'hostedUrl' => 'https://jobs.lever.co/gitlab/abc',
            'categories' => ['location' => 'Manila'], 'descriptionPlain' => 'Same job',
        ]])]);

        app(JobIngestor::class)->ingest($this->source());
        $result = app(JobIngestor::class)->ingest(JobSource::factory()->create(['name' => 'GitLab', 'driver' => JobSourceDriver::Lever, 'identifier' => 'gitlab']));

        $this->assertSame(1, $result->duplicates);
        $this->assertSame(1, JobPosting::query()->count());
    }

    public function test_the_title_filter_keeps_only_matching_roles(): void
    {
        $this->fakeGreenhouse([
            $this->greenhouseJob(1, 'Senior Software Engineer'),
            $this->greenhouseJob(2, 'Director, FP&A'),
            $this->greenhouseJob(3, 'Frontend Developer'),
        ]);
        $source = $this->source();
        $source->update(['title_filter' => 'engineer, developer']);

        $result = app(JobIngestor::class)->ingest($source);

        $this->assertSame(1, $result->filtered);
        $this->assertEqualsCanonicalizing(['Senior Software Engineer', 'Frontend Developer'], JobPosting::query()->pluck('title')->all());
    }

    public function test_aggregator_sources_can_be_added_without_keywords(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('sources.store'), [
                'name' => 'Arbeitnow',
                'driver' => 'arbeitnow',
                'identifier' => null,
                'title_filter' => 'developer',
                'fetch_interval_minutes' => 360,
                'is_enabled' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->create())
            ->post(route('sources.store'), ['name' => 'Ashby', 'driver' => 'ashby', 'identifier' => null, 'fetch_interval_minutes' => 360])
            ->assertSessionHasErrors('identifier');
    }

    public function test_failures_are_recorded_on_the_source(): void
    {
        Http::fake(['boards-api.greenhouse.io/*' => Http::response('Not found', 404)]);
        $source = $this->source();

        try {
            app(JobIngestor::class)->ingest($source);
            $this->fail('Expected the request to fail.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame(JobSourceStatus::Failed, $source->fresh()?->last_status);
        $this->assertNotNull($source->fresh()?->last_error);
    }

    private function source(): JobSource
    {
        return JobSource::factory()->create(['name' => 'GitLab', 'driver' => JobSourceDriver::Greenhouse, 'identifier' => 'gitlab']);
    }

    /**
     * @param  list<array<string, mixed>>  $jobs
     */
    private function fakeGreenhouse(array $jobs): void
    {
        Http::fake(['boards-api.greenhouse.io/*' => Http::response(['jobs' => $jobs])]);
    }

    /**
     * @return array<string, mixed>
     */
    private function greenhouseJob(int $id, string $title, string $location = 'Remote'): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'company_name' => 'GitLab',
            'location' => ['name' => $location],
            'absolute_url' => "https://job-boards.greenhouse.io/gitlab/jobs/{$id}",
            'first_published' => '2026-11-01T10:00:00-04:00',
            // Greenhouse double-encodes its HTML.
            'content' => htmlspecialchars('<p>Requirements:</p><ul><li>Strong PHP and Laravel</li><li>Postgres</li></ul>'),
        ];
    }
}
