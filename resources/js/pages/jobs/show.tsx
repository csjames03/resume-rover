import { Head, usePoll } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Banknote,
    BriefcaseBusiness,
    CalendarDays,
    ExternalLink,
    Globe,
    MapPin,
} from 'lucide-react';
import { useEffect } from 'react';
import { ApplicationPanel } from '@/components/jobs/application-panel';
import { CompanyAvatar } from '@/components/company-avatar';
import { ScoreRing } from '@/components/fit-badge';
import { EmailPanel } from '@/components/jobs/email-panel';
import { MatchPanel } from '@/components/jobs/match-panel';
import { TailoringPanel } from '@/components/jobs/tailoring-panel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate, timeAgo } from '@/lib/format';
import { index } from '@/routes/jobs';
import type {
    Application,
    ApplicationEmail,
    JobPosting,
    Option,
    TailoredResume,
} from '@/types';

type Props = {
    posting: JobPosting;
    application: Application | null;
    tailoredResumes: TailoredResume[];
    applicationEmails: ApplicationEmail[];
    statuses: Option[];
    ai: { explain: boolean; tailor: boolean };
};

export default function JobShow({
    posting,
    application,
    tailoredResumes,
    applicationEmails,
    statuses,
    ai,
}: Props) {
    // Keep refreshing while an AI explanation or a draft is being generated.
    const waiting =
        posting.match?.explanation_status === 'pending' ||
        tailoredResumes.some((t) => t.status === 'generating');

    const { start, stop } = usePoll(
        3000,
        { only: ['posting', 'tailoredResumes'] },
        { autoStart: false },
    );
    useEffect(() => {
        if (waiting) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [waiting, start, stop]);

    const facts: { icon: LucideIcon; label: string | null }[] = [
        { icon: Globe, label: posting.remote_label },
        { icon: MapPin, label: posting.location },
        { icon: BriefcaseBusiness, label: posting.employment_type },
        { icon: Banknote, label: posting.salary_text },
    ];

    return (
        <>
            <Head title={`${posting.title} · ${posting.company}`} />

            <div className="mx-auto grid w-full max-w-7xl flex-1 gap-6 p-4 md:p-8 lg:grid-cols-[minmax(0,1fr)_380px]">
                <article className="min-w-0 space-y-6">
                    <header className="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                        <div className="flex items-start gap-4">
                            <CompanyAvatar name={posting.company} size="lg" />
                            <div className="min-w-0 flex-1 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium text-muted-foreground">
                                        {posting.company}
                                    </span>
                                    {posting.closed_at && (
                                        <Badge variant="outline">
                                            Closed{' '}
                                            {formatDate(posting.closed_at)}
                                        </Badge>
                                    )}
                                    {posting.source?.attribution && (
                                        <Badge variant="secondary">
                                            {posting.source.attribution}
                                        </Badge>
                                    )}
                                </div>
                                <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                                    {posting.title}
                                </h1>
                            </div>
                            {posting.match && (
                                <ScoreRing
                                    score={posting.match.score}
                                    category={posting.match.category}
                                    size={64}
                                    className="hidden sm:inline-flex"
                                />
                            )}
                        </div>

                        <ul className="mt-5 flex flex-wrap gap-2">
                            {facts
                                .filter((fact) => fact.label)
                                .map((fact) => (
                                    <li
                                        key={fact.label}
                                        className="inline-flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-sm"
                                    >
                                        <fact.icon
                                            className="size-3.5 text-muted-foreground"
                                            aria-hidden
                                        />
                                        {fact.label}
                                    </li>
                                ))}
                        </ul>

                        <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <CalendarDays
                                    className="size-3.5"
                                    aria-hidden
                                />
                                Posted {formatDate(posting.posted_at)} · found{' '}
                                {timeAgo(posting.first_seen_at)} via{' '}
                                {posting.source?.name}
                            </p>
                            <Button asChild variant="outline" size="sm">
                                <a
                                    href={posting.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    View original posting <ExternalLink />
                                </a>
                            </Button>
                        </div>
                    </header>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                About the role
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {/* Stored as plain text server-side, so no third-party HTML is rendered. */}
                            <div className="max-w-prose text-[15px] leading-7 whitespace-pre-line text-foreground/90">
                                {posting.description}
                            </div>
                        </CardContent>
                    </Card>
                </article>

                <aside className="space-y-4 lg:sticky lg:top-20 lg:self-start">
                    <MatchPanel
                        postingId={posting.id}
                        match={posting.match}
                        aiEnabled={ai.explain}
                    />
                    <ApplicationPanel
                        postingId={posting.id}
                        application={application}
                        statuses={statuses}
                        tailoredResumes={tailoredResumes}
                    />
                    <TailoringPanel
                        postingId={posting.id}
                        tailoredResumes={tailoredResumes}
                        aiEnabled={ai.tailor}
                        canTailor={posting.match !== null}
                    />
                    <EmailPanel
                        postingId={posting.id}
                        emails={applicationEmails}
                        canDraft={posting.match !== null}
                    />
                </aside>
            </div>
        </>
    );
}

JobShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Jobs', href: index() },
        { title: props.posting.title, href: '#' },
    ],
});
