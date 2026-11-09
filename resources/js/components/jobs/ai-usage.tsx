import { Clock, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import { formatWait } from '@/lib/format';
import type { AiUsage } from '@/types';

// Matches AiAvailability::QUEUE_ABSORBS_SECONDS: shorter waits are handled by the queue.
const QUEUE_ABSORBS_SECONDS = 60;

export type AiGate = {
    /** Why the AI button is disabled right now, or null when it can be used. */
    blockedReason: string | null;
    remaining: number;
    limit: number;
};

/**
 * Turns the server's AI status into a button state, counting down a provider
 * wait in the browser so the button re-enables on its own.
 */
export function useAiGate(usage: AiUsage): AiGate {
    const [busyFor, setBusyFor] = useState(usage.busy_for);

    useEffect(() => {
        setBusyFor(usage.busy_for);

        if (usage.busy_for <= 0) {
            return;
        }

        const endsAt = Date.now() + usage.busy_for * 1000;
        const timer = setInterval(() => {
            const left = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));
            setBusyFor(left);

            if (left === 0) {
                clearInterval(timer);
            }
        }, 1000);

        return () => clearInterval(timer);
    }, [usage.busy_for]);

    let blockedReason: string | null = null;

    if (usage.remaining <= 0) {
        blockedReason = `You've used all ${usage.limit} AI actions for today. More in ${formatWait(usage.resets_in)}.`;
    } else if (busyFor > QUEUE_ABSORBS_SECONDS) {
        blockedReason = `The free AI tier is at its limit. Try again in ${formatWait(busyFor)}.`;
    }

    return { blockedReason, remaining: usage.remaining, limit: usage.limit };
}

/**
 * The line under an AI button: what's left today, or why it's paused.
 * `fallback` describes what happens instead (e.g. "Drafts use the template").
 */
export function AiUsageNote({
    gate,
    fallback,
}: {
    gate: AiGate;
    fallback?: string;
}) {
    if (gate.blockedReason) {
        return (
            <p
                role="status"
                className="flex items-start gap-1.5 rounded-md bg-amber-500/10 px-2.5 py-1.5 text-xs text-amber-800 dark:text-amber-300"
            >
                <Clock className="mt-0.5 size-3.5 shrink-0" aria-hidden />
                <span>
                    {gate.blockedReason}
                    {fallback && ` ${fallback}`}
                </span>
            </p>
        );
    }

    return (
        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <Sparkles className="size-3.5" aria-hidden />
            {gate.remaining} of {gate.limit} AI actions left today
        </p>
    );
}
