import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    ArrowRight,
    ArrowUpRight,
    CalendarClock,
    CircleDashed,
    FileUp,
    Search,
    Sparkles,
    Target,
    TrendingUp,
} from 'lucide-react';
import { CompanyAvatar } from '@/components/company-avatar';
import { EmptyState } from '@/components/empty-state';
import { ScoreRing } from '@/components/fit-badge';
import { PageHeader } from '@/components/page-header';
import { STATUS_STYLES } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as applicationsIndex } from '@/routes/applications';
import { index as jobsIndex, show as showJob } from '@/routes/jobs';
import { show as showResume } from '@/routes/resume';
import { index as sourcesIndex } from '@/routes/sources';
import type {
    Application,
    ApplicationStatus,
    JobPosting,
    MatchCategory,
    ParseStatus,
} from '@/types';

type Props = {
    resume: {
        filename: string;
        parse_status: ParseStatus;
        skills_count: number;
    } | null;
    matchCounts: { category: MatchCategory; label: string; total: number }[];
    topMatches: JobPosting[];
    pipeline: { status: ApplicationStatus; label: string; total: number }[];
    upcoming: Application[];
    sources: { total: number; failing: string[] };
    aiEnabled: boolean;
};

const CATEGORY_CARDS: Record<
    MatchCategory,
    { icon: LucideIcon; tone: string; hint: string }
> = {
    strong: {
        icon: Target,
        tone: 'bg-fit-strong/12 text-fit-strong',
        hint: 'Apply to these first',
    },
    relevant: {
        icon: TrendingUp,
        tone: 'bg-fit-relevant/14 text-fit-relevant',
        hint: 'Worth a closer look',
    },
    low: {
        icon: CircleDashed,
        tone: 'bg-fit-low/14 text-fit-low',
        hint: 'Probably not for you',
    },
};

function greeting(): string {
    const hour = new Date().getHours();

    return hour < 12
        ? 'Good morning'
        : hour < 18
          ? 'Good afternoon'
          : 'Good evening';
}

export default function Dashboard({
    resume,
    matchCounts,
    topMatches,
    pipeline,
    upcoming,
    sources,
    aiEnabled,
}: Props) {
    const { auth } = usePage().props;
    const firstName = auth.user.name.split(' ')[0];

    return (
        <>
            <Head title="Dashboard" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
                <PageHeader
                    eyebrow={new Intl.DateTimeFormat(undefined, {
                        weekday: 'long',
                        month: 'long',
                        day: 'numeric',
                    }).format(new Date())}
                    title={`${greeting()}, ${firstName}`}
                    description="Here are the roles that deserve your attention, and what to do next."
                    actions={
                        <Button asChild>
                            <Link href={jobsIndex()}>
                                <Search /> Browse jobs
                            </Link>
                        </Button>
                    }
                />

                <SetupAlerts
                    resume={resume}
                    sources={sources}
                    aiEnabled={aiEnabled}
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    {matchCounts.map((count) => (
                        <StatCard key={count.category} {...count} />
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="gap-4 lg:col-span-2">
                        <CardHeader className="flex flex-row items-start justify-between gap-4">
                            <div className="space-y-1.5">
                                <CardTitle>Best untracked matches</CardTitle>
                                <CardDescription>
                                    Strong and relevant roles you haven't saved
                                    yet.
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={jobsIndex()}>
                                    All jobs <ArrowRight />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="px-3">
                            {topMatches.length === 0 ? (
                                <p className="px-3 py-6 text-sm text-muted-foreground">
                                    No strong or relevant matches yet. They
                                    appear here once your résumé is parsed and
                                    sources are fetched.
                                </p>
                            ) : (
                                <ul className="space-y-1">
                                    {topMatches.map((posting) => (
                                        <li key={posting.id}>
                                            <MatchRow posting={posting} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-6">
                        <PipelineCard pipeline={pipeline} />
                        <NextActionsCard upcoming={upcoming} />
                    </div>
                </div>
            </div>
        </>
    );
}

function StatCard({ category, label, total }: Props['matchCounts'][number]) {
    const card = CATEGORY_CARDS[category];

    return (
        <Link
            href={jobsIndex({ query: { category } })}
            className="group relative overflow-hidden rounded-xl border bg-card p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <div className="flex items-start justify-between">
                <span
                    className={cn(
                        'flex size-10 items-center justify-center rounded-lg',
                        card.tone,
                    )}
                >
                    <card.icon className="size-5" aria-hidden />
                </span>
                <ArrowUpRight
                    className="size-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
                    aria-hidden
                />
            </div>
            <p className="mt-4 text-3xl font-bold tracking-tight tabular-nums">
                {total}
            </p>
            <p className="text-sm font-medium">{label}</p>
            <p className="text-xs text-muted-foreground">{card.hint}</p>
        </Link>
    );
}

function MatchRow({ posting }: { posting: JobPosting }) {
    return (
        <Link
            href={showJob(posting.id)}
            className="flex items-center gap-4 rounded-lg px-3 py-2.5 transition-colors hover:bg-muted/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            prefetch
        >
            <CompanyAvatar name={posting.company} />
            <div className="min-w-0 flex-1">
                <p className="truncate font-semibold">{posting.title}</p>
                <p className="truncate text-sm text-muted-foreground">
                    {posting.company} · {posting.remote_label}
                    {posting.location && ` · ${posting.location}`}
                </p>
            </div>
            {posting.match && (
                <ScoreRing
                    score={posting.match.score}
                    category={posting.match.category}
                />
            )}
        </Link>
    );
}

function PipelineCard({ pipeline }: Pick<Props, 'pipeline'>) {
    const total = pipeline.reduce((sum, stage) => sum + stage.total, 0);

    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle>Pipeline</CardTitle>
                <CardDescription>
                    {total === 0
                        ? 'Save a job to start tracking it.'
                        : `${total} active ${total === 1 ? 'application' : 'applications'}`}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {total > 0 && (
                    <div
                        className="flex h-2 gap-0.5 overflow-hidden rounded-full bg-muted"
                        aria-hidden
                    >
                        {pipeline
                            .filter((stage) => stage.total > 0)
                            .map((stage) => (
                                <div
                                    key={stage.status}
                                    className={STATUS_STYLES[stage.status].dot}
                                    style={{
                                        width: `${(stage.total / total) * 100}%`,
                                    }}
                                />
                            ))}
                    </div>
                )}
                <ul className="space-y-0.5 text-sm">
                    {pipeline.map((stage) => (
                        <li key={stage.status}>
                            <Link
                                href={jobsIndex({
                                    query: { status: stage.status },
                                })}
                                className="-mx-2 flex items-center gap-2.5 rounded-md px-2 py-1.5 transition-colors hover:bg-muted/70"
                            >
                                <span
                                    className={cn(
                                        'size-2 rounded-full',
                                        STATUS_STYLES[stage.status].dot,
                                    )}
                                    aria-hidden
                                />
                                <span className="flex-1 text-muted-foreground">
                                    {stage.label}
                                </span>
                                <span className="font-semibold tabular-nums">
                                    {stage.total}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

function NextActionsCard({ upcoming }: Pick<Props, 'upcoming'>) {
    return (
        <Card className="gap-4">
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Next actions</CardTitle>
                <Button variant="ghost" size="sm" asChild>
                    <Link href={applicationsIndex()}>
                        Tracker <ArrowRight />
                    </Link>
                </Button>
            </CardHeader>
            <CardContent>
                {upcoming.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nothing scheduled. Add a next action on any application.
                    </p>
                ) : (
                    <ul className="space-y-3 text-sm">
                        {upcoming.map((application) => (
                            <li key={application.id} className="flex gap-3">
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <CalendarClock
                                        className="size-4"
                                        aria-hidden
                                    />
                                </span>
                                <div className="min-w-0">
                                    <p className="font-medium">
                                        {application.next_action ?? 'Follow up'}
                                    </p>
                                    <p className="truncate text-muted-foreground">
                                        {formatDate(application.next_action_at)}{' '}
                                        · {application.posting?.company}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function SetupAlerts({
    resume,
    sources,
    aiEnabled,
}: Pick<Props, 'resume' | 'sources' | 'aiEnabled'>) {
    if (!resume) {
        return (
            <EmptyState
                icon={FileUp}
                title="Start with your résumé"
                description="Upload a PDF or DOCX. Every job is scored against it, so this comes first."
                action={
                    <Button asChild>
                        <Link href={showResume()}>Upload résumé</Link>
                    </Button>
                }
            />
        );
    }

    return (
        <div className="grid gap-3 empty:hidden">
            {resume.parse_status === 'failed' && (
                <Alert variant="destructive">
                    <AlertTriangle />
                    <AlertTitle>Your résumé couldn't be read</AlertTitle>
                    <AlertDescription>
                        <Link href={showResume()} className="underline">
                            Open the résumé page
                        </Link>{' '}
                        to see why and try another file.
                    </AlertDescription>
                </Alert>
            )}
            {sources.failing.length > 0 && (
                <Alert>
                    <AlertTriangle />
                    <AlertTitle>Some job sources failed to refresh</AlertTitle>
                    <AlertDescription>
                        {sources.failing.join(', ')}.{' '}
                        <Link href={sourcesIndex()} className="underline">
                            Check sources
                        </Link>
                    </AlertDescription>
                </Alert>
            )}
            {!aiEnabled && (
                <Alert>
                    <Sparkles />
                    <AlertTitle>AI features are off</AlertTitle>
                    <AlertDescription>
                        Scoring works without AI. Add a Gemini, Groq or
                        OpenRouter key to your .env file for explanations and
                        tailored résumés.
                    </AlertDescription>
                </Alert>
            )}
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
