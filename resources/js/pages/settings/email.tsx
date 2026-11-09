import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Mail, ShieldCheck } from 'lucide-react';
import EmailConnectionController from '@/actions/App/Http/Controllers/Settings/EmailConnectionController';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { timeAgo } from '@/lib/format';
import { show } from '@/routes/email-connection';

type Props = {
    configured: boolean;
    redirectUri: string;
    connection: {
        email: string;
        connected_at: string | null;
        needs_reconnect: boolean;
    } | null;
};

export default function EmailSettings({
    configured,
    redirectUri,
    connection,
}: Props) {
    const disconnect = () => {
        if (
            confirm(
                'Disconnect Gmail? Access is revoked at Google and the app can no longer send for you.',
            )
        ) {
            router.delete(EmailConnectionController.destroy().url);
        }
    };

    // OAuth needs a full page redirect to Google, so this is a plain link, not an Inertia visit.
    const connectHref = EmailConnectionController.redirect().url;

    return (
        <>
            <Head title="Email" />

            <h1 className="sr-only">Email</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Gmail"
                    description="Send application emails from your own Gmail address after you review them."
                />

                <div className="flex gap-3 rounded-lg border p-4 text-sm">
                    <ShieldCheck className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                    <div className="space-y-1">
                        <p className="font-medium">Send-only access</p>
                        <p className="text-muted-foreground">
                            The app asks Google only for permission to send
                            email. It cannot read, search or delete anything in
                            your inbox. Nothing is sent until you click Send on
                            a draft.
                        </p>
                    </div>
                </div>

                {!configured && (
                    <Alert>
                        <AlertTriangle />
                        <AlertTitle>Google sign-in isn't set up yet</AlertTitle>
                        <AlertDescription>
                            <p>
                                Add <code>GOOGLE_CLIENT_ID</code> and{' '}
                                <code>GOOGLE_CLIENT_SECRET</code> to{' '}
                                <code>.env</code> (see the README). Use this as
                                the authorized redirect URI:
                            </p>
                            <code className="mt-1 block rounded bg-muted px-2 py-1 break-all">
                                {redirectUri}
                            </code>
                        </AlertDescription>
                    </Alert>
                )}

                {configured && !connection && (
                    <Button asChild>
                        <a href={connectHref}>
                            <Mail /> Connect Gmail
                        </a>
                    </Button>
                )}

                {connection && (
                    <div className="space-y-4">
                        {connection.needs_reconnect && (
                            <Alert variant="destructive">
                                <AlertTriangle />
                                <AlertTitle>Reconnect needed</AlertTitle>
                                <AlertDescription>
                                    Google stopped accepting this connection
                                    (access was revoked, or the 7-day limit for
                                    apps in testing mode passed).
                                </AlertDescription>
                            </Alert>
                        )}

                        <div className="flex items-center justify-between gap-4 rounded-lg border p-4 text-sm">
                            <div>
                                <p className="font-medium">
                                    {connection.email}
                                </p>
                                <p className="text-muted-foreground">
                                    Connected {timeAgo(connection.connected_at)}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                {connection.needs_reconnect && configured && (
                                    <Button size="sm" asChild>
                                        <a href={connectHref}>Reconnect</a>
                                    </Button>
                                )}
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={disconnect}
                                >
                                    Disconnect
                                </Button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

EmailSettings.layout = {
    breadcrumbs: [{ title: 'Email', href: show() }],
};
