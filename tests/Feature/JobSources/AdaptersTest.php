<?php

namespace Tests\Feature\JobSources;

use App\Enums\JobSourceDriver;
use App\Models\JobSource;
use App\Services\JobSources\Data\JobPostingData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Each adapter turns its board's real response shape into one JobPostingData.
 */
class AdaptersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{JobSourceDriver, ?string, string, mixed, array<string, string>}>
     */
    public static function boards(): array
    {
        return [
            'ashby' => [JobSourceDriver::Ashby, 'supabase', 'api.ashbyhq.com/*', ['jobs' => [[
                'id' => 'a1', 'title' => 'Backend Engineer', 'jobUrl' => 'https://jobs.ashbyhq.com/supabase/a1', 'isListed' => true,
                'isRemote' => true, 'location' => 'Remote', 'employmentType' => 'FullTime', 'descriptionPlain' => 'Postgres all day',
                'compensation' => ['compensationTierSummary' => '$150K – $200K'], 'publishedAt' => '2026-11-01T00:00:00Z',
            ], ['id' => 'hidden', 'title' => 'Unlisted', 'jobUrl' => 'https://x', 'isListed' => false]]],
                ['title' => 'Backend Engineer', 'remote' => 'remote', 'salary' => '$150K – $200K', 'employment' => 'full_time']],

            'recruitee' => [JobSourceDriver::Recruitee, 'bunq', 'bunq.recruitee.com/*', ['offers' => [[
                'id' => 7, 'title' => 'Frontend Developer', 'company_name' => 'bunq', 'careers_url' => 'https://careers.bunq.com/o/fe',
                'location' => 'Amsterdam', 'hybrid' => true, 'description' => '<p>Build UI</p>', 'requirements' => '<ul><li>React</li></ul>',
                'employment_type_code' => 'fulltime_permanent', 'published_at' => '2026-11-01 10:00:00 UTC',
            ]]], ['title' => 'Frontend Developer', 'remote' => 'hybrid', 'company' => 'bunq', 'employment' => 'full_time']],

            'remote ok' => [JobSourceDriver::RemoteOk, 'react', 'remoteok.com/*', [
                ['legal' => 'Please link back to Remote OK'],
                ['id' => '11', 'position' => 'React Developer', 'company' => 'Acme', 'url' => 'https://remoteOK.com/remote-jobs/11', 'tags' => ['react'], 'description' => '<p>Hooks</p>', 'date' => '2026-11-01T00:00:00+00:00', 'salary_min' => 90000, 'salary_max' => 120000],
                ['id' => '12', 'position' => 'Manufacturing Manager', 'company' => 'Other', 'url' => 'https://remoteOK.com/remote-jobs/12', 'tags' => ['manager']],
            ], ['title' => 'React Developer', 'remote' => 'remote', 'salary' => 'USD 90,000 – 120,000']],

            'jobicy' => [JobSourceDriver::Jobicy, 'react', 'jobicy.com/*', ['jobs' => [[
                'id' => 5, 'jobTitle' => 'Senior React Engineer', 'companyName' => 'Headway', 'url' => 'https://jobicy.com/jobs/5',
                'jobGeo' => 'USA', 'jobType' => ['Full-Time'], 'jobDescription' => '<p>React</p>', 'pubDate' => '2026-11-07T16:33:25+00:00',
                'salaryMin' => 100000, 'salaryMax' => 150000, 'salaryCurrency' => 'USD', 'salaryPeriod' => 'yearly',
            ]]], ['title' => 'Senior React Engineer', 'remote' => 'remote', 'employment' => 'full_time']],

            'himalayas' => [JobSourceDriver::Himalayas, 'full stack', 'himalayas.app/*', ['jobs' => [[
                'title' => 'Full Stack Engineer', 'companyName' => 'Acme', 'guid' => 'https://himalayas.app/companies/acme/jobs/fse',
                'applicationLink' => 'https://himalayas.app/companies/acme/jobs/fse', 'employmentType' => 'Full Time',
                'locationRestrictions' => ['Philippines'], 'description' => '<p>Laravel + React</p>', 'pubDate' => 1794060844,
            ]]], ['title' => 'Full Stack Engineer', 'remote' => 'remote', 'location' => 'Remote: Philippines']],

            'arbeitnow' => [JobSourceDriver::Arbeitnow, null, 'www.arbeitnow.com/*', ['data' => [[
                'slug' => 'php-dev-berlin-1', 'title' => 'PHP Developer', 'company_name' => 'tide', 'url' => 'https://www.arbeitnow.com/jobs/1',
                'remote' => false, 'location' => 'Berlin', 'description' => '&lt;p&gt;Laravel&lt;/p&gt;', 'created_at' => 1794110132, 'job_types' => [],
            ]], 'links' => ['next' => null]], ['title' => 'PHP Developer', 'location' => 'Berlin', 'description' => 'Laravel']],
        ];
    }

    /**
     * @param  array<string, string>  $expected
     */
    #[DataProvider('boards')]
    public function test_adapters_normalize_their_board(JobSourceDriver $driver, ?string $identifier, string $url, mixed $payload, array $expected): void
    {
        Http::fake([$url => Http::response($payload)]);
        $source = JobSource::factory()->create(['driver' => $driver, 'identifier' => $identifier, 'name' => 'Source']);

        /** @var list<JobPostingData> $postings */
        $postings = iterator_to_array(app($driver->adapter())->fetch($source), false);

        $this->assertCount(1, $postings, 'unlisted, unrelated or legal-notice items are skipped');
        $posting = $postings[0];

        $this->assertSame($expected['title'], $posting->title);
        $this->assertNotSame('', $posting->externalId);
        $this->assertStringStartsWith('https://', $posting->url);
        $this->assertStringNotContainsString('<', $posting->description, 'HTML is converted to text');

        foreach (['remote' => $posting->remoteType->value, 'salary' => $posting->salaryText, 'company' => $posting->company,
            'employment' => $posting->employmentType?->value, 'location' => $posting->location] as $key => $actual) {
            if (isset($expected[$key])) {
                $this->assertSame($expected[$key], $actual, "{$key} mismatch");
            }
        }

        if (isset($expected['description'])) {
            $this->assertStringContainsString($expected['description'], $posting->description);
        }
    }

    public function test_recruitee_rejects_identifiers_that_are_not_a_subdomain(): void
    {
        Http::fake();
        $source = JobSource::factory()->create(['driver' => JobSourceDriver::Recruitee, 'identifier' => 'evil.example.com/x']);

        $this->expectException(InvalidArgumentException::class);
        iterator_to_array(app(JobSourceDriver::Recruitee->adapter())->fetch($source));
    }

    public function test_every_driver_has_an_adapter_and_rules(): void
    {
        foreach (JobSourceDriver::cases() as $driver) {
            $this->assertTrue(class_exists($driver->adapter()), "{$driver->value} adapter missing");
            $this->assertNotSame('', $driver->identifierHint());
            $this->assertSame(1, preg_match('/^.+$/', $driver->identifierPattern()));
        }
    }
}
