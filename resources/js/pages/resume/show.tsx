import { Deferred, Head, router, usePoll } from '@inertiajs/react';
import { AlertTriangle, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import ResumeController from '@/actions/App/Http/Controllers/ResumeController';
import { PageHeader } from '@/components/page-header';
import { SectionCard, SectionForm } from '@/components/resume/section-editor';
import { SkillsEditor } from '@/components/resume/skills-editor';
import { UploadCard } from '@/components/resume/upload-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { formatBytes, timeAgo } from '@/lib/format';
import { show } from '@/routes/resume';
import type { Option, Resume, ResumeSection } from '@/types';

type Props = {
    resume: Resume | null;
    sectionTypes: Option[];
    skillSuggestions?: string[];
};

// Display order of section groups on the page.
const GROUPS: { type: string; title: string }[] = [
    { type: 'summary', title: 'Summary' },
    { type: 'experience', title: 'Experience' },
    { type: 'project', title: 'Projects' },
    { type: 'education', title: 'Education' },
    { type: 'certification', title: 'Certifications' },
    { type: 'link', title: 'Links' },
    { type: 'other', title: 'Other' },
];

export default function ResumeShow({
    resume,
    sectionTypes,
    skillSuggestions,
}: Props) {
    const isParsing =
        resume?.parse_status === 'pending' ||
        resume?.parse_status === 'processing';

    // Refresh only the résumé prop while the background parser runs.
    const { start, stop } = usePoll(
        2000,
        { only: ['resume'] },
        { autoStart: false },
    );
    useEffect(() => {
        if (isParsing) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [isParsing, start, stop]);

    return (
        <>
            <Head title="Résumé" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-8">
                <PageHeader
                    title="Your résumé"
                    description="The master profile every job is scored against. Fix anything the parser got wrong."
                    actions={resume && <ResumeActions resume={resume} />}
                />

                {!resume && <UploadCard />}

                {resume && isParsing && (
                    <Card>
                        <CardContent className="flex items-center gap-3 py-8">
                            <Spinner />
                            <p className="text-sm">
                                Reading{' '}
                                <strong>{resume.original_filename}</strong>.
                                This usually takes a few seconds…
                            </p>
                        </CardContent>
                    </Card>
                )}

                {resume?.parse_status === 'failed' && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>We couldn't read this file</AlertTitle>
                        <AlertDescription>
                            {resume.parse_error ?? 'Try another file.'}
                        </AlertDescription>
                    </Alert>
                )}

                {resume?.parse_status === 'parsed' && (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>Skills</CardTitle>
                                <CardDescription>
                                    Matching compares these against each job's
                                    requirements.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Deferred
                                    data="skillSuggestions"
                                    fallback={<Skeleton className="h-9 w-80" />}
                                >
                                    <SkillsEditor
                                        skills={resume.skills ?? []}
                                        suggestions={skillSuggestions}
                                    />
                                </Deferred>
                            </CardContent>
                        </Card>

                        <Sections
                            sections={resume.sections ?? []}
                            sectionTypes={sectionTypes}
                        />
                    </>
                )}
            </div>
        </>
    );
}

function ResumeActions({ resume }: { resume: Resume }) {
    const reparse = () =>
        router.post(
            ResumeController.reparse(resume.id).url,
            {},
            { preserveScroll: true },
        );

    const destroy = () => {
        if (
            confirm(
                'Delete this résumé file and everything parsed from it? Scores will be removed too.',
            )
        ) {
            router.delete(ResumeController.destroy(resume.id).url);
        }
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            <span className="hidden text-xs text-muted-foreground md:inline">
                {resume.original_filename} · {formatBytes(resume.file_size)} ·
                uploaded {timeAgo(resume.uploaded_at)}
                {resume.parser && ` · parsed by ${resume.parser}`}
            </span>
            <UploadCard compact />
            <Button variant="outline" size="sm" onClick={reparse}>
                <RefreshCw /> Re-parse
            </Button>
            <Button
                variant="ghost"
                size="sm"
                onClick={destroy}
                aria-label="Delete résumé"
            >
                <Trash2 />
            </Button>
        </div>
    );
}

function Sections({
    sections,
    sectionTypes,
}: {
    sections: ResumeSection[];
    sectionTypes: Option[];
}) {
    const [adding, setAdding] = useState(false);

    return (
        <div className="space-y-8">
            {GROUPS.map((group) => {
                const items = sections.filter(
                    (section) => section.type === group.type,
                );

                if (items.length === 0) {
                    return null;
                }

                return (
                    <section key={group.type} className="space-y-3">
                        <h3 className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                            {group.title}
                        </h3>
                        {items.map((section) => (
                            <SectionCard
                                key={section.id}
                                section={section}
                                sectionTypes={sectionTypes}
                            />
                        ))}
                    </section>
                );
            })}

            {adding ? (
                <SectionForm
                    sectionTypes={sectionTypes}
                    onDone={() => setAdding(false)}
                />
            ) : (
                <Button variant="outline" onClick={() => setAdding(true)}>
                    <Plus /> Add a section
                </Button>
            )}
        </div>
    );
}

ResumeShow.layout = {
    breadcrumbs: [{ title: 'Résumé', href: show() }],
};
