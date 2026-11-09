import { Link, router } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import ApplicationEmailController from '@/actions/App/Http/Controllers/ApplicationEmailController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { timeAgo } from '@/lib/format';
import type { ApplicationEmail } from '@/types';

const STATUS_LABEL: Record<ApplicationEmail['status'], string> = {
    draft: 'Draft',
    sent: 'Sent',
    failed: 'Not sent',
};

/**
 * "Apply by email": drafts are written in the app and only sent after review.
 */
export function EmailPanel({
    postingId,
    emails,
    canDraft,
}: {
    postingId: number;
    emails: ApplicationEmail[];
    canDraft: boolean;
}) {
    const draft = () =>
        router.post(ApplicationEmailController.store(postingId).url);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Apply by email</CardTitle>
                <CardDescription>
                    Get a draft written from your résumé, review it, then send
                    it from your Gmail.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {emails.length > 0 && (
                    <ul className="space-y-1">
                        {emails.map((email) => (
                            <li key={email.id}>
                                <Link
                                    href={ApplicationEmailController.show(
                                        email.id,
                                    )}
                                    className="-mx-2 flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted/50"
                                >
                                    <span className="min-w-0 truncate">
                                        {email.to ?? 'No recipient yet'}
                                        <span className="text-muted-foreground">
                                            {' '}
                                            ·{' '}
                                            {timeAgo(
                                                email.sent_at ??
                                                    email.created_at,
                                            )}
                                        </span>
                                    </span>
                                    <Badge
                                        variant={
                                            email.status === 'sent'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {STATUS_LABEL[email.status]}
                                    </Badge>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                <Button variant="outline" onClick={draft} disabled={!canDraft}>
                    <Mail />{' '}
                    {emails.length > 0
                        ? 'Write another email'
                        : 'Draft application email'}
                </Button>
                {!canDraft && (
                    <p className="text-xs text-muted-foreground">
                        Upload your résumé first.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
