<?php

namespace Database\Seeders;

use App\Enums\JobSourceDriver;
use App\Models\JobSource;
use Illuminate\Database\Seeder;

/**
 * Starter sources: public, permitted feeds only. Add your own in the app.
 * Safe to re-run: existing sources are left as they are.
 */
class JobSourceSeeder extends Seeder
{
    /** Big employers post many non-engineering roles; keep the technical ones. */
    private const TECH_TITLES = 'engineer, developer, software, frontend, front end, backend, back end, full stack, fullstack, devops, data, web';

    public function run(): void
    {
        $sources = [
            // [name, driver, identifier, title filter]
            ['GitLab', JobSourceDriver::Greenhouse, 'gitlab', self::TECH_TITLES],
            ['Vercel', JobSourceDriver::Greenhouse, 'vercel', null],
            ['Palantir', JobSourceDriver::Lever, 'palantir', self::TECH_TITLES],
            ['Supabase', JobSourceDriver::Ashby, 'supabase', null],
            ['Linear', JobSourceDriver::Ashby, 'linear', null],
            ['Remotive: developer', JobSourceDriver::Remotive, 'developer', null],
            ['Remote OK: react', JobSourceDriver::RemoteOk, 'react', null],
            ['Jobicy: react', JobSourceDriver::Jobicy, 'react', null],
            ['Himalayas: full stack', JobSourceDriver::Himalayas, 'full stack', null],
            ['Arbeitnow: latest', JobSourceDriver::Arbeitnow, null, 'developer, engineer'],
        ];

        foreach ($sources as [$name, $driver, $identifier, $titleFilter]) {
            JobSource::query()->firstOrCreate(
                ['driver' => $driver, 'identifier' => $identifier],
                [
                    'name' => $name,
                    'title_filter' => $titleFilter,
                    'fetch_interval_minutes' => max(360, $driver->minimumIntervalMinutes()),
                    'compliance_notes' => $this->notes($driver),
                ],
            );
        }
    }

    private function notes(JobSourceDriver $driver): string
    {
        return match ($driver) {
            JobSourceDriver::Greenhouse, JobSourceDriver::Lever, JobSourceDriver::Ashby, JobSourceDriver::Recruitee => "Public {$driver->label()} job board API, meant for displaying a company's openings.",
            JobSourceDriver::Remotive => 'Remotive API: link back, credit Remotive, max 4 fetches a day.',
            JobSourceDriver::RemoteOk => 'Remote OK API: link back to the Remote OK URL and credit Remote OK (no logo).',
            JobSourceDriver::Jobicy => 'Jobicy API: credit Jobicy with a direct link; apply buttons go to the original URL.',
            JobSourceDriver::Himalayas => 'Himalayas public jobs API; credited as the source.',
            JobSourceDriver::Arbeitnow => 'Arbeitnow free job board API; credited as the source.',
        };
    }
}
