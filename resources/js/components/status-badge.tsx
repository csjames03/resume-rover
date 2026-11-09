import { cn } from '@/lib/utils';
import type { ApplicationStatus } from '@/types';

/**
 * One color per pipeline stage. Shared by the badge and the dashboard
 * pipeline bar so a stage looks the same everywhere.
 */
export const STATUS_STYLES: Record<
    ApplicationStatus,
    { label: string; dot: string; badge: string }
> = {
    saved: {
        label: 'Saved',
        dot: 'bg-slate-400',
        badge: 'bg-slate-500/10 text-slate-700 ring-slate-500/20 dark:text-slate-300',
    },
    preparing: {
        label: 'Preparing',
        dot: 'bg-violet-500',
        badge: 'bg-violet-500/10 text-violet-700 ring-violet-500/25 dark:text-violet-300',
    },
    applied: {
        label: 'Applied',
        dot: 'bg-blue-500',
        badge: 'bg-blue-500/10 text-blue-700 ring-blue-500/25 dark:text-blue-300',
    },
    interviewing: {
        label: 'Interviewing',
        dot: 'bg-amber-500',
        badge: 'bg-amber-500/12 text-amber-800 ring-amber-500/30 dark:text-amber-300',
    },
    offer: {
        label: 'Offer',
        dot: 'bg-emerald-500',
        badge: 'bg-emerald-500/12 text-emerald-700 ring-emerald-500/30 dark:text-emerald-300',
    },
    rejected: {
        label: 'Rejected',
        dot: 'bg-rose-500',
        badge: 'bg-rose-500/10 text-rose-700 ring-rose-500/25 dark:text-rose-300',
    },
    withdrawn: {
        label: 'Withdrawn',
        dot: 'bg-zinc-400',
        badge: 'bg-zinc-500/10 text-zinc-600 ring-zinc-500/20 dark:text-zinc-400',
    },
};

export function StatusBadge({
    status,
    className,
}: {
    status: ApplicationStatus;
    className?: string;
}) {
    const style = STATUS_STYLES[status];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
                style.badge,
                className,
            )}
        >
            <span
                className={cn('size-1.5 rounded-full', style.dot)}
                aria-hidden
            />
            {style.label}
        </span>
    );
}
