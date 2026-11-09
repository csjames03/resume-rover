import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Download, Info } from 'lucide-react';
import { useEffect } from 'react';
import TailoredResumeController from '@/actions/App/Http/Controllers/TailoredResumeController';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatDateRange } from '@/lib/format';
import { index as jobsIndex, show as showJob } from '@/routes/jobs';
import type { Resume, TailoredResume, TailoredSection } from '@/types';

type Props = {
    tailored: TailoredResume;
    original: Resume;
};

export default function TailoredResumeShow({ tailored, original }: Props) {
    const { start, stop } = usePoll(
        3000,
        { only: ['tailored'] },
        { autoStart: false },
    );
    useEffect(() => {
        if (tailored.status === 'generating') {
            start();
        } else {
            stop();
        }

        return stop;
    }, [tailored.status, start, stop]);

    return (
        <>
            <Head title={`Tailored résumé v${tailored.version}`} />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
                <PageHeader
                    title={`Tailored résumé · v${tailored.version}`}
                    description={
                        tailored.posting && (
                            <>
                                For{' '}
                                <Link
                                    href={showJob(tailored.posting.id)}
                                    className="underline"
                                >
                                    {tailored.posting.title} at{' '}
                                    {tailored.posting.company}
                                </Link>
                                {tailored.provider &&
                                    ` · drafted by ${tailored.provider}`}
                            </>
                        )
                    }
                    actions={<DraftActions tailored={tailored} />}
                />

                {tailored.status === 'generating' && (
                    <Card>
                        <CardContent className="flex items-center gap-3 py-8">
                            <Spinner />
                            <p className="text-sm">
                                Drafting from your résumé. This takes up to a
                                minute…
                            </p>
                        </CardContent>
                    </Card>
                )}

                {tailored.status === 'failed' && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>The draft couldn't be generated</AlertTitle>
                        <AlertDescription>
                            {tailored.error ?? 'Try again from the job page.'}
                        </AlertDescription>
                    </Alert>
                )}

                {tailored.content && (
                    <>
                        <FlaggedClaims claims={tailored.flagged_claims} />
                        <div className="grid gap-6 xl:grid-cols-2">
                            <OriginalResume resume={original} />
                            <TailoredEditor tailored={tailored} />
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

function DraftActions({ tailored }: { tailored: TailoredResume }) {
    if (tailored.status === 'approved') {
        return (
            <>
                <Badge className="gap-1">
                    <CheckCircle2 /> Approved
                </Badge>
                <Button asChild variant="outline" size="sm">
                    <a
                        href={
                            TailoredResumeController.download(tailored.id).url
                        }
                    >
                        <Download /> Download .md
                    </a>
                </Button>
            </>
        );
    }

    if (tailored.status !== 'draft') {
        return null;
    }

    const approve = () => {
        if (
            confirm(
                'Approve this draft? Check every flagged line first. Approved drafts can no longer be edited.',
            )
        ) {
            router.post(
                TailoredResumeController.approve(tailored.id).url,
                {},
                { preserveScroll: true },
            );
        }
    };

    return (
        <Button size="sm" onClick={approve}>
            <CheckCircle2 /> I've reviewed it, approve
        </Button>
    );
}

function FlaggedClaims({
    claims,
}: {
    claims: TailoredResume['flagged_claims'];
}) {
    if (claims.length === 0) {
        return (
            <Alert>
                <Info />
                <AlertTitle>Every line traces back to your résumé</AlertTitle>
                <AlertDescription>
                    Still read it through before you send it anywhere.
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert>
            <AlertTriangle />
            <AlertTitle>
                {claims.length} item{claims.length === 1 ? '' : 's'} need your
                attention
            </AlertTitle>
            <AlertDescription>
                <ul className="mt-1 list-disc space-y-1 pl-4">
                    {claims.map((claim, i) => (
                        <li key={i}>
                            <span className="text-foreground">
                                "{claim.text}"
                            </span>
                            : {claim.reason}
                        </li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}

function OriginalResume({ resume }: { resume: Resume }) {
    const sections = (resume.sections ?? []).filter((s) =>
        ['experience', 'project', 'education', 'certification'].includes(
            s.type,
        ),
    );

    return (
        <Card className="bg-muted/30">
            <CardHeader>
                <CardTitle className="text-base">Original</CardTitle>
            </CardHeader>
            <CardContent className="space-y-5 text-sm">
                {sections.map((section) => (
                    <div key={section.id}>
                        <p className="font-medium">
                            {[section.title, section.organization]
                                .filter(Boolean)
                                .join(' — ')}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {formatDateRange(
                                section.start_date,
                                section.end_date,
                                section.is_current,
                            )}
                        </p>
                        <ul className="mt-1.5 list-disc space-y-1 pl-5">
                            {section.bullets.map((bullet) => (
                                <li key={bullet.id}>{bullet.text}</li>
                            ))}
                        </ul>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function TailoredEditor({ tailored }: { tailored: TailoredResume }) {
    const content = tailored.content!;
    const editable = tailored.status === 'draft';

    const form = useForm({
        summary: content.summary ?? '',
        sections: content.sections.map((section) => ({
            bullets: section.bullets.map((bullet) => bullet.text),
        })),
    });

    const setBullet = (s: number, b: number, text: string) => {
        const sections = form.data.sections.map((section, i) =>
            i === s
                ? {
                      bullets: section.bullets.map((t, j) =>
                          j === b ? text : t,
                      ),
                  }
                : section,
        );
        form.setData('sections', sections);
    };

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        form.submit(TailoredResumeController.update(tailored.id), {
            preserveScroll: true,
        });
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-base">Tailored</CardTitle>
                {editable && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={save}
                        disabled={!form.isDirty || form.processing}
                    >
                        Save edits
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-5 text-sm">
                {content.skills.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                        {content.skills.map((skill) => (
                            <Badge key={skill} variant="secondary">
                                {skill}
                            </Badge>
                        ))}
                    </div>
                )}

                {(content.summary !== null || editable) && (
                    <div className="space-y-1">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Summary
                        </p>
                        {editable ? (
                            <Textarea
                                value={form.data.summary}
                                onChange={(e) =>
                                    form.setData('summary', e.target.value)
                                }
                                rows={3}
                            />
                        ) : (
                            <p>{content.summary}</p>
                        )}
                        <Flags flags={content.summary_flags} />
                    </div>
                )}

                {content.sections.map((section, s) => (
                    <TailoredSectionView
                        key={section.section_id}
                        section={section}
                        editable={editable}
                        values={form.data.sections[s]?.bullets ?? []}
                        onChange={(b, text) => setBullet(s, b, text)}
                    />
                ))}
            </CardContent>
        </Card>
    );
}

function TailoredSectionView({
    section,
    editable,
    values,
    onChange,
}: {
    section: TailoredSection;
    editable: boolean;
    values: string[];
    onChange: (bullet: number, text: string) => void;
}) {
    return (
        <div>
            <p className="font-medium">
                {[section.title, section.organization]
                    .filter(Boolean)
                    .join(' — ')}
            </p>
            {section.dates && (
                <p className="text-xs text-muted-foreground">{section.dates}</p>
            )}
            <ul className="mt-1.5 space-y-2">
                {section.bullets.map((bullet, b) => (
                    <li key={b} className="space-y-1">
                        <div className="flex items-start gap-2">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <span className="mt-1.5 shrink-0 cursor-help rounded bg-muted px-1 text-[10px] text-muted-foreground">
                                        src
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent className="max-w-sm">
                                    <p className="mb-1 font-medium">
                                        Based on:
                                    </p>
                                    {bullet.sources.map((source, i) => (
                                        <p key={i}>• {source}</p>
                                    ))}
                                </TooltipContent>
                            </Tooltip>
                            {editable ? (
                                <Textarea
                                    value={values[b] ?? ''}
                                    onChange={(e) =>
                                        onChange(b, e.target.value)
                                    }
                                    rows={2}
                                    className="min-h-0"
                                />
                            ) : (
                                <p>{bullet.text}</p>
                            )}
                        </div>
                        <Flags flags={bullet.flags} />
                    </li>
                ))}
            </ul>
        </div>
    );
}

function Flags({ flags }: { flags: string[] }) {
    if (flags.length === 0) {
        return null;
    }

    return (
        <ul className="space-y-0.5 pl-7 text-xs text-fit-relevant">
            {flags.map((flag, i) => (
                <li key={i} className="flex gap-1">
                    <AlertTriangle className="mt-0.5 size-3 shrink-0" /> {flag}
                </li>
            ))}
        </ul>
    );
}

TailoredResumeShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Jobs', href: jobsIndex() },
        ...(props.tailored.posting
            ? [
                  {
                      title: props.tailored.posting.title,
                      href: showJob(props.tailored.posting.id),
                  },
              ]
            : []),
        { title: `Tailored v${props.tailored.version}`, href: '#' },
    ],
});
