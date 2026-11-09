import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { timeAgo } from '@/lib/format';
import { show } from '@/routes/ai-providers';

type Provider = {
    name: string;
    label: string;
    model: string;
    configured: boolean;
    cooling_down_until: string | null;
};

type ProviderEvent = {
    id: number;
    provider: string;
    model: string;
    task: string;
    outcome: string;
    latency_ms: number | null;
    error_message: string | null;
    created_at: string;
};

type Props = {
    providers: Provider[];
    priority: string[];
    events: ProviderEvent[];
};

export default function AiProviders({ providers, priority, events }: Props) {
    const ordered = [...providers].sort(
        (a, b) => rank(priority, a.name) - rank(priority, b.name),
    );

    return (
        <>
            <Head title="AI providers" />

            <h1 className="sr-only">AI providers</h1>

            <div className="space-y-8">
                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="AI providers"
                        description="Tried in this order. A rate-limited or failing provider is benched for a while and the next one takes over."
                    />

                    <ol className="space-y-2">
                        {ordered.map((provider, i) => (
                            <li
                                key={provider.name}
                                className="flex items-center justify-between gap-4 rounded-lg border p-3 text-sm"
                            >
                                <div>
                                    <p className="font-medium">
                                        {i + 1}. {provider.label}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {provider.model}
                                    </p>
                                </div>
                                <ProviderState provider={provider} />
                            </li>
                        ))}
                    </ol>

                    <p className="text-xs text-muted-foreground">
                        Set <code>GEMINI_API_KEY</code>,{' '}
                        <code>GROQ_API_KEY</code> or{' '}
                        <code>OPENROUTER_API_KEY</code> in <code>.env</code>,
                        and the order with <code>AI_PROVIDER_PRIORITY</code>.
                        Free tiers and model names change; check each provider's
                        current terms.
                    </p>
                </div>

                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Recent calls"
                        description="Metadata only. Prompts and résumé text are never logged."
                    />

                    {events.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No AI calls yet.
                        </p>
                    ) : (
                        <div className="rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>When</TableHead>
                                        <TableHead>Provider</TableHead>
                                        <TableHead>Task</TableHead>
                                        <TableHead>Outcome</TableHead>
                                        <TableHead className="text-right">
                                            Latency
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {events.map((event) => (
                                        <TableRow key={event.id}>
                                            <TableCell className="text-muted-foreground">
                                                {timeAgo(event.created_at)}
                                            </TableCell>
                                            <TableCell>
                                                {event.provider}
                                            </TableCell>
                                            <TableCell>{event.task}</TableCell>
                                            <TableCell
                                                title={
                                                    event.error_message ??
                                                    undefined
                                                }
                                            >
                                                <Badge
                                                    variant={
                                                        event.outcome ===
                                                        'success'
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {event.outcome.replace(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {event.latency_ms !== null
                                                    ? `${event.latency_ms} ms`
                                                    : '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

function ProviderState({ provider }: { provider: Provider }) {
    if (!provider.configured) {
        return <Badge variant="outline">No API key</Badge>;
    }

    if (provider.cooling_down_until) {
        return (
            <Badge variant="secondary">
                Benched, back {timeAgo(provider.cooling_down_until)}
            </Badge>
        );
    }

    return <Badge>Ready</Badge>;
}

function rank(priority: string[], name: string): number {
    const index = priority.indexOf(name);

    return index === -1 ? priority.length : index;
}

AiProviders.layout = {
    breadcrumbs: [{ title: 'AI providers', href: show() }],
};
