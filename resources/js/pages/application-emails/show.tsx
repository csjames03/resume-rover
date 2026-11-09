import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Paperclip,
    Send,
    Trash2,
} from 'lucide-react';
import ApplicationEmailController from '@/actions/App/Http/Controllers/ApplicationEmailController';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import { show as showEmailSettings } from '@/routes/email-connection';
import { index as jobsIndex, show as showJob } from '@/routes/jobs';
import type { ApplicationEmail } from '@/types';

type Props = {
    email: ApplicationEmail;
    gmail: { email: string; needs_reconnect: boolean } | null;
};

export default function ApplicationEmailShow({ email, gmail }: Props) {
    const sent = email.status === 'sent';

    return (
        <>
            <Head title={`Email: ${email.subject}`} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4 md:p-8">
                <PageHeader
                    title={
                        sent ? 'Application email sent' : 'Review your email'
                    }
                    description={
                        email.posting && (
                            <>
                                For{' '}
                                <Link
                                    href={showJob(email.posting.id)}
                                    className="underline"
                                >
                                    {email.posting.title} at{' '}
                                    {email.posting.company}
                                </Link>
                                {email.drafted_by &&
                                    ` · drafted by ${email.drafted_by}`}
                            </>
                        )
                    }
                />

                {sent ? (
                    <SentEmail email={email} />
                ) : (
                    <>
                        <GmailStatus gmail={gmail} />
                        {email.status === 'failed' && email.error && (
                            <Alert variant="destructive">
                                <AlertTriangle />
                                <AlertTitle>Last attempt failed</AlertTitle>
                                <AlertDescription>
                                    {email.error}
                                </AlertDescription>
                            </Alert>
                        )}
                        <DraftEditor
                            email={email}
                            canSend={gmail !== null && !gmail.needs_reconnect}
                        />
                    </>
                )}
            </div>
        </>
    );
}

function GmailStatus({ gmail }: Pick<Props, 'gmail'>) {
    if (gmail && !gmail.needs_reconnect) {
        return (
            <p className="text-sm text-muted-foreground">
                Will be sent from <strong>{gmail.email}</strong>.
            </p>
        );
    }

    return (
        <Alert>
            <AlertTriangle />
            <AlertTitle>
                {gmail ? 'Reconnect Gmail to send' : 'Connect Gmail to send'}
            </AlertTitle>
            <AlertDescription>
                <span>
                    You can edit and save this draft now.{' '}
                    <Link href={showEmailSettings()} className="underline">
                        Go to Settings → Email
                    </Link>{' '}
                    to connect.
                </span>
            </AlertDescription>
        </Alert>
    );
}

function DraftEditor({
    email,
    canSend,
}: {
    email: ApplicationEmail;
    canSend: boolean;
}) {
    const form = useForm({
        to: email.to ?? '',
        subject: email.subject,
        body: email.body,
        attach_resume: email.attach_resume,
    });

    const save = (e?: React.FormEvent) => {
        e?.preventDefault();
        form.transform((data) => ({ ...data, to: data.to || null }));
        form.submit(ApplicationEmailController.update(email.id), {
            preserveScroll: true,
        });
    };

    const send = () => {
        const recipient = form.data.to.trim();

        if (!recipient) {
            form.setError('to', 'Add the recipient before sending.');

            return;
        }

        if (
            !confirm(
                `Send this email to ${recipient} now? This can't be undone.`,
            )
        ) {
            return;
        }

        // Save any unsaved edits first, then send exactly what was saved.
        form.transform((data) => ({ ...data, to: data.to || null }));
        form.submit(ApplicationEmailController.update(email.id), {
            preserveScroll: true,
            onSuccess: () =>
                router.post(
                    ApplicationEmailController.send(email.id).url,
                    {},
                    { preserveScroll: true },
                ),
        });
    };

    const discard = () => {
        if (confirm('Discard this draft?')) {
            router.delete(ApplicationEmailController.destroy(email.id).url);
        }
    };

    return (
        <form onSubmit={save} className="space-y-5">
            {email.flags.length > 0 && (
                <Alert>
                    <AlertTriangle />
                    <AlertTitle>Check before sending</AlertTitle>
                    <AlertDescription>
                        <ul className="mt-1 list-disc space-y-1 pl-4">
                            {email.flags.map((flag, i) => (
                                <li key={i}>{flag}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}

            <div className="grid gap-1.5">
                <Label htmlFor="email-to">To</Label>
                <Input
                    id="email-to"
                    type="email"
                    value={form.data.to}
                    onChange={(e) => form.setData('to', e.target.value)}
                    placeholder="recruiter@company.com"
                />
                {!email.to && (
                    <p className="text-xs text-muted-foreground">
                        No address was found in the posting. Check the original
                        ad or the company's careers page.
                    </p>
                )}
                <InputError message={form.errors.to} />
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="email-subject">Subject</Label>
                <Input
                    id="email-subject"
                    value={form.data.subject}
                    onChange={(e) => form.setData('subject', e.target.value)}
                />
                <InputError message={form.errors.subject} />
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="email-body">Message</Label>
                <Textarea
                    id="email-body"
                    value={form.data.body}
                    onChange={(e) => form.setData('body', e.target.value)}
                    rows={14}
                    className="font-[inherit]"
                />
                <InputError message={form.errors.body} />
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="email-attach"
                    checked={form.data.attach_resume}
                    onCheckedChange={(checked) =>
                        form.setData('attach_resume', checked === true)
                    }
                />
                <Label htmlFor="email-attach" className="font-normal">
                    <Paperclip className="inline size-3.5" /> Attach my résumé
                    {email.resume_filename && (
                        <span className="text-muted-foreground">
                            {' '}
                            ({email.resume_filename})
                        </span>
                    )}
                </Label>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-4">
                <div className="flex gap-2">
                    <Button
                        type="button"
                        onClick={send}
                        disabled={!canSend || form.processing}
                    >
                        <Send /> Send
                    </Button>
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={!form.isDirty || form.processing}
                    >
                        Save draft
                    </Button>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    className="text-destructive"
                    onClick={discard}
                >
                    <Trash2 /> Discard
                </Button>
            </div>
        </form>
    );
}

function SentEmail({ email }: { email: ApplicationEmail }) {
    return (
        <>
            <Alert>
                <CheckCircle2 />
                <AlertTitle>Sent {formatDate(email.sent_at)}</AlertTitle>
                <AlertDescription>
                    The job is marked Applied in your tracker. A copy is in your
                    Gmail "Sent" folder.
                </AlertDescription>
            </Alert>
            <Card>
                <CardContent className="space-y-3 pt-6 text-sm">
                    <p>
                        <span className="text-muted-foreground">To:</span>{' '}
                        {email.to}
                    </p>
                    <p>
                        <span className="text-muted-foreground">Subject:</span>{' '}
                        {email.subject}
                    </p>
                    {email.attach_resume && email.resume_filename && (
                        <p className="flex items-center gap-1 text-muted-foreground">
                            <Paperclip className="size-3.5" />{' '}
                            {email.resume_filename}
                        </p>
                    )}
                    <p className="border-t pt-3 whitespace-pre-line">
                        {email.body}
                    </p>
                </CardContent>
            </Card>
        </>
    );
}

ApplicationEmailShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Jobs', href: jobsIndex() },
        ...(props.email.posting
            ? [
                  {
                      title: props.email.posting.title,
                      href: showJob(props.email.posting.id),
                  },
              ]
            : []),
        { title: 'Email', href: '#' },
    ],
});
