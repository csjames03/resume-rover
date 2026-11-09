import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BriefcaseBusiness,
    Check,
    FileSearch,
    Minus,
    ShieldCheck,
    Wand2,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { CompanyAvatar } from '@/components/company-avatar';
import { FitBadge, ScoreRing } from '@/components/fit-badge';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

const FEATURES = [
    {
        icon: FileSearch,
        title: 'Reads your résumé',
        body: 'Upload a PDF or DOCX, then review and correct the parsed profile.',
    },
    {
        icon: BriefcaseBusiness,
        title: 'Scores every job',
        body: 'Strong fit, relevant or low match, with the exact strengths and gaps behind each score.',
    },
    {
        icon: Wand2,
        title: 'Tailors honestly',
        body: 'Drafts a job-specific résumé from your real experience. Every line cites its source.',
    },
    {
        icon: ShieldCheck,
        title: 'Plays by the rules',
        body: 'Official public job feeds only, and nothing is ever sent without your approval.',
    },
];

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Welcome" />

            <div className="relative isolate flex min-h-svh flex-col overflow-hidden bg-background">
                {/* Soft brand glow behind the hero; purely decorative. */}
                <div
                    aria-hidden
                    className="absolute inset-x-0 -top-40 -z-10 h-[560px] bg-[radial-gradient(60%_60%_at_50%_0%,color-mix(in_oklch,var(--primary)_18%,transparent),transparent)]"
                />

                <header className="mx-auto flex w-full max-w-6xl items-center justify-between p-6">
                    <div className="flex items-center gap-2.5 text-lg font-bold tracking-tight">
                        <span className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        {name}
                    </div>
                    <nav className="flex gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>Open dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>Get started</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-20 px-6 pt-10 pb-20 lg:pt-16">
                    <section className="grid items-center gap-12 lg:grid-cols-[1.1fr_1fr]">
                        <div className="space-y-6">
                            <p className="inline-flex items-center gap-2 rounded-full border bg-card px-3 py-1 text-xs font-medium text-muted-foreground shadow-sm">
                                <span className="size-1.5 rounded-full bg-fit-strong" />
                                Explainable job matching
                            </p>
                            <h1 className="text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                                Spend your job search on the roles that{' '}
                                <span className="text-primary">fit.</span>
                            </h1>
                            <p className="max-w-xl text-lg text-muted-foreground">
                                A personal command center that finds openings,
                                explains how well each one matches your actual
                                experience, and keeps every application
                                organized.
                            </p>
                            <div className="flex flex-wrap gap-3">
                                <Button size="lg" asChild>
                                    <Link
                                        href={
                                            auth.user ? dashboard() : register()
                                        }
                                    >
                                        {auth.user
                                            ? 'Open dashboard'
                                            : 'Create your account'}
                                        <ArrowRight />
                                    </Link>
                                </Button>
                                {!auth.user && (
                                    <Button size="lg" variant="outline" asChild>
                                        <Link href={login()}>Log in</Link>
                                    </Button>
                                )}
                            </div>
                        </div>

                        <PreviewCard />
                    </section>

                    <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {FEATURES.map((feature) => (
                            <div
                                key={feature.title}
                                className="rounded-xl border bg-card p-5 shadow-sm"
                            >
                                <span className="mb-4 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <feature.icon
                                        className="size-5"
                                        aria-hidden
                                    />
                                </span>
                                <h2 className="font-semibold">
                                    {feature.title}
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {feature.body}
                                </p>
                            </div>
                        ))}
                    </section>
                </main>
            </div>
        </>
    );
}

/**
 * A static sample of the match panel, so visitors see what a score looks like.
 */
function PreviewCard() {
    return (
        <div
            className="relative rounded-2xl border bg-card p-6 shadow-xl shadow-primary/5"
            aria-label="Example of a scored job"
        >
            <div className="flex items-start gap-4">
                <CompanyAvatar name="Northwind Labs" size="lg" />
                <div className="min-w-0 flex-1">
                    <p className="text-sm text-muted-foreground">
                        Northwind Labs · Remote
                    </p>
                    <p className="text-lg font-bold tracking-tight">
                        Frontend Engineer
                    </p>
                    <FitBadge category="strong" className="mt-2" />
                </div>
                <ScoreRing score={86} category="strong" size={64} />
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <p className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        Strengths
                    </p>
                    <ul className="space-y-1.5 text-sm">
                        {[
                            'React, TypeScript',
                            '3+ years experience',
                            'Title match',
                        ].map((item) => (
                            <li key={item} className="flex items-center gap-2">
                                <Check
                                    className="size-4 text-fit-strong"
                                    aria-hidden
                                />
                                {item}
                            </li>
                        ))}
                    </ul>
                </div>
                <div>
                    <p className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        Gaps
                    </p>
                    <ul className="space-y-1.5 text-sm">
                        {['GraphQL (nice to have)'].map((item) => (
                            <li key={item} className="flex items-center gap-2">
                                <Minus
                                    className="size-4 text-fit-relevant"
                                    aria-hidden
                                />
                                {item}
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </div>
    );
}
