<?php

namespace App\Enums;

use App\Services\JobSources\Adapters\ArbeitnowAdapter;
use App\Services\JobSources\Adapters\AshbyAdapter;
use App\Services\JobSources\Adapters\GreenhouseAdapter;
use App\Services\JobSources\Adapters\HimalayasAdapter;
use App\Services\JobSources\Adapters\JobicyAdapter;
use App\Services\JobSources\Adapters\LeverAdapter;
use App\Services\JobSources\Adapters\RecruiteeAdapter;
use App\Services\JobSources\Adapters\RemoteOkAdapter;
use App\Services\JobSources\Adapters\RemotiveAdapter;
use App\Services\JobSources\Contracts\JobSourceAdapter;

/**
 * Every supported job board, and the rules that come with it (terms of use,
 * credit line, refresh limit, what the identifier means). Adding a board =
 * one adapter class + one case here.
 */
enum JobSourceDriver: string
{
    use HasValues;

    // Company hiring pages (identifier = the company's board name)
    case Greenhouse = 'greenhouse';
    case Lever = 'lever';
    case Ashby = 'ashby';
    case Recruitee = 'recruitee';

    // Job boards that aggregate many companies (identifier = search keywords)
    case Remotive = 'remotive';
    case RemoteOk = 'remoteok';
    case Jobicy = 'jobicy';
    case Himalayas = 'himalayas';
    case Arbeitnow = 'arbeitnow';

    /**
     * @return class-string<JobSourceAdapter>
     */
    public function adapter(): string
    {
        return match ($this) {
            self::Greenhouse => GreenhouseAdapter::class,
            self::Lever => LeverAdapter::class,
            self::Ashby => AshbyAdapter::class,
            self::Recruitee => RecruiteeAdapter::class,
            self::Remotive => RemotiveAdapter::class,
            self::RemoteOk => RemoteOkAdapter::class,
            self::Jobicy => JobicyAdapter::class,
            self::Himalayas => HimalayasAdapter::class,
            self::Arbeitnow => ArbeitnowAdapter::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::RemoteOk => 'Remote OK',
            default => ucfirst($this->value),
        };
    }

    /**
     * Company boards need to know which company; aggregators work without a keyword.
     */
    public function requiresIdentifier(): bool
    {
        return in_array($this, [self::Greenhouse, self::Lever, self::Ashby, self::Recruitee], true);
    }

    /**
     * Allowed identifier shape. Recruitee's goes into a hostname, so it is strict.
     */
    public function identifierPattern(): string
    {
        return match ($this) {
            self::Recruitee => '/^[a-z0-9][a-z0-9-]*$/i',
            self::Greenhouse, self::Lever, self::Ashby => '/^[\w.-]+$/',
            default => '/^[\w .+#-]+$/u',
        };
    }

    /**
     * What the identifier means for this driver, shown in the UI.
     */
    public function identifierHint(): string
    {
        return match ($this) {
            self::Greenhouse => 'Board name from boards.greenhouse.io/<name>, e.g. "gitlab"',
            self::Lever => 'Company from jobs.lever.co/<company>, e.g. "palantir"',
            self::Ashby => 'Company from jobs.ashbyhq.com/<company>, e.g. "supabase"',
            self::Recruitee => 'Subdomain from <company>.recruitee.com, e.g. "bunq"',
            self::Remotive => 'Search keywords, e.g. "laravel" (optional)',
            self::RemoteOk => 'One tag, e.g. "react" (optional)',
            self::Jobicy => 'One tag, e.g. "php" (optional)',
            self::Himalayas => 'Search keywords, e.g. "full stack" (optional)',
            self::Arbeitnow => 'Not used: fetches the latest jobs. Use the title filter to narrow them.',
        };
    }

    /**
     * Shortest refresh interval the source's terms (or good manners) allow, in minutes.
     */
    public function minimumIntervalMinutes(): int
    {
        return match ($this) {
            self::Remotive => 360, // Remotive asks for at most 4 requests a day.
            self::RemoteOk, self::Jobicy, self::Himalayas, self::Arbeitnow => 180,
            default => 60,
        };
    }

    /**
     * Credit line required by the board's API terms (shown next to each job).
     */
    public function attribution(): ?string
    {
        return match ($this) {
            self::Remotive => 'via Remotive',
            self::RemoteOk => 'via Remote OK',
            self::Jobicy => 'via Jobicy',
            self::Himalayas => 'via Himalayas',
            self::Arbeitnow => 'via Arbeitnow',
            default => null,
        };
    }
}
