import { router, useForm } from '@inertiajs/react';
import { Archive, ArchiveRestore, BookmarkPlus } from 'lucide-react';
import ApplicationController from '@/actions/App/Http/Controllers/ApplicationController';
import InputError from '@/components/input-error';
import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { timeAgo } from '@/lib/format';
import type { Application, Option, TailoredResume } from '@/types';

/**
 * Track this job: status, next action, notes, and the history of changes.
 */
export function ApplicationPanel({
    postingId,
    application,
    statuses,
    tailoredResumes,
}: {
    postingId: number;
    application: Application | null;
    statuses: Option[];
    tailoredResumes: TailoredResume[];
}) {
    if (!application) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Tracker</CardTitle>
                    <CardDescription>
                        Save this job to track its status and notes.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button
                        onClick={() =>
                            router.post(
                                ApplicationController.store(postingId).url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <BookmarkPlus /> Save job
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <TrackedApplication
            application={application}
            statuses={statuses}
            tailoredResumes={tailoredResumes}
        />
    );
}

function TrackedApplication({
    application,
    statuses,
    tailoredResumes,
}: {
    application: Application;
    statuses: Option[];
    tailoredResumes: TailoredResume[];
}) {
    const form = useForm({
        next_action: application.next_action ?? '',
        next_action_at: application.next_action_at ?? '',
        notes: application.notes ?? '',
    });

    const update = (changes: {
        status?: string;
        tailored_resume_id?: number | null;
    }) =>
        router.patch(
            ApplicationController.update(application.id).url,
            changes,
            { preserveScroll: true },
        );

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            next_action_at: data.next_action_at || null,
        }));
        form.submit(ApplicationController.update(application.id), {
            preserveScroll: true,
        });
    };

    const approvedVersions: Option[] = tailoredResumes
        .filter((t) => t.status === 'approved')
        .map((t) => ({ value: String(t.id), label: `Tailored v${t.version}` }));

    const archiveAction = application.archived_at
        ? ApplicationController.restore(application.id)
        : ApplicationController.archive(application.id);

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-2">
                <CardTitle>Tracker</CardTitle>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                        router.post(
                            archiveAction.url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    {application.archived_at ? (
                        <>
                            <ArchiveRestore /> Restore
                        </>
                    ) : (
                        <>
                            <Archive /> Archive
                        </>
                    )}
                </Button>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
                <div className="grid gap-1.5">
                    <Label htmlFor="application-status">Status</Label>
                    <OptionSelect
                        id="application-status"
                        value={application.status}
                        options={statuses}
                        onChange={(status) => status && update({ status })}
                        className="w-full"
                    />
                </div>

                {approvedVersions.length > 0 && (
                    <div className="grid gap-1.5">
                        <Label htmlFor="application-resume">Résumé sent</Label>
                        <OptionSelect
                            id="application-resume"
                            value={
                                application.tailored_resume_id?.toString() ??
                                null
                            }
                            options={approvedVersions}
                            anyLabel="Master résumé"
                            onChange={(id) =>
                                update({
                                    tailored_resume_id: id ? Number(id) : null,
                                })
                            }
                            className="w-full"
                        />
                    </div>
                )}

                <form onSubmit={save} className="space-y-3">
                    <div className="grid grid-cols-[1fr_auto] gap-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="next-action">Next action</Label>
                            <Input
                                id="next-action"
                                value={form.data.next_action}
                                onChange={(e) =>
                                    form.setData('next_action', e.target.value)
                                }
                                placeholder="Follow up with recruiter"
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="next-action-at">Due</Label>
                            <Input
                                id="next-action-at"
                                type="date"
                                value={form.data.next_action_at}
                                onChange={(e) =>
                                    form.setData(
                                        'next_action_at',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <InputError
                        message={
                            form.errors.next_action ??
                            form.errors.next_action_at
                        }
                    />
                    <div className="grid gap-1.5">
                        <Label htmlFor="notes">Notes</Label>
                        <Textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                            rows={3}
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save notes
                    </Button>
                </form>

                {application.events && application.events.length > 0 && (
                    <div>
                        <h4 className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            History
                        </h4>
                        <ol className="space-y-1.5 border-l pl-4">
                            {application.events.map((event) => (
                                <li
                                    key={event.id}
                                    className="text-muted-foreground"
                                >
                                    <span className="text-foreground">
                                        {event.to_status
                                            ? `${event.from_status ? `${event.from_status} → ` : ''}${event.to_status}`
                                            : event.label}
                                    </span>{' '}
                                    · {timeAgo(event.created_at)}
                                </li>
                            ))}
                        </ol>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
