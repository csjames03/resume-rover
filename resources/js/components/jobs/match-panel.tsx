import { router } from '@inertiajs/react';
import { Check, Minus, Sparkles, X } from 'lucide-react';
import MatchExplanationController from '@/actions/App/Http/Controllers/MatchExplanationController';
import { FitBadge, ScoreBar } from '@/components/fit-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import type { JobMatch, MatchPoint } from '@/types';

/**
 * Score, category, and every reason behind them.
 */
export function MatchPanel({
    postingId,
    match,
    aiEnabled,
}: {
    postingId: number;
    match: JobMatch | null;
    aiEnabled: boolean;
}) {
    if (!match) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Fit</CardTitle>
                    <CardDescription>
                        Not scored yet. Upload and review your résumé first.
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const mustGaps = match.gaps.filter((gap) => gap.kind === 'must_have');
    const otherGaps = match.gaps.filter((gap) => gap.kind !== 'must_have');

    return (
        <Card>
            <CardHeader className="space-y-3">
                <div className="flex items-center justify-between gap-2">
                    <CardTitle>Fit</CardTitle>
                    <FitBadge category={match.category} score={match.score} />
                </div>
                <ScoreBar score={match.score} category={match.category} />
                {match.is_stale && (
                    <p className="text-xs text-muted-foreground">
                        Your profile changed; this score is being refreshed.
                    </p>
                )}
            </CardHeader>
            <CardContent className="space-y-5 text-sm">
                <PointList
                    title="Strengths"
                    points={match.strengths}
                    icon={<Check className="size-4 text-fit-strong" />}
                    empty="No clear matches found."
                />
                <PointList
                    title="Missing must-haves"
                    points={mustGaps}
                    icon={<X className="size-4 text-destructive" />}
                />
                <PointList
                    title="Other gaps"
                    points={otherGaps}
                    icon={<Minus className="size-4 text-muted-foreground" />}
                />

                <Explanation
                    postingId={postingId}
                    match={match}
                    aiEnabled={aiEnabled}
                />
            </CardContent>
        </Card>
    );
}

function PointList({
    title,
    points,
    icon,
    empty,
}: {
    title: string;
    points: MatchPoint[];
    icon: React.ReactNode;
    empty?: string;
}) {
    if (points.length === 0 && !empty) {
        return null;
    }

    return (
        <div>
            <h4 className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {title}
            </h4>
            {points.length === 0 ? (
                <p className="text-muted-foreground">{empty}</p>
            ) : (
                <ul className="space-y-1.5">
                    {points.map((point, i) => (
                        <li
                            key={`${point.type}-${point.label}-${i}`}
                            className="flex gap-2"
                            title={point.detail}
                        >
                            <span className="mt-0.5 shrink-0">{icon}</span>
                            <span>
                                {point.label}
                                {point.kind === 'nice_to_have' && (
                                    <Badge variant="outline" className="ml-2">
                                        nice to have
                                    </Badge>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Explanation({
    postingId,
    match,
    aiEnabled,
}: {
    postingId: number;
    match: JobMatch;
    aiEnabled: boolean;
}) {
    const request = () =>
        router.post(
            MatchExplanationController.store(postingId).url,
            {},
            { preserveScroll: true },
        );

    if (match.explanation_status === 'pending') {
        return (
            <p className="flex items-center gap-2 text-muted-foreground">
                <Spinner /> Writing an explanation…
            </p>
        );
    }

    if (match.explanation) {
        return (
            <div className="rounded-lg bg-muted/50 p-3">
                <h4 className="mb-1 flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    <Sparkles className="size-3.5" /> AI summary
                </h4>
                <p className="whitespace-pre-line">{match.explanation}</p>
            </div>
        );
    }

    if (!aiEnabled) {
        return null;
    }

    return (
        <div className="space-y-1">
            {match.explanation_status === 'failed' && (
                <p className="text-xs text-destructive">
                    The AI providers were unavailable. Try again in a minute.
                </p>
            )}
            <Button variant="outline" size="sm" onClick={request}>
                <Sparkles /> Explain this score
            </Button>
        </div>
    );
}
