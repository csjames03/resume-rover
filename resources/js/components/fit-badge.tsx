import { cn } from '@/lib/utils';
import type { MatchCategory } from '@/types';

const STYLES: Record<MatchCategory, { label: string; className: string }> = {
    strong: {
        label: 'Strong fit',
        className: 'bg-fit-strong/12 text-fit-strong ring-fit-strong/30',
    },
    relevant: {
        label: 'Relevant',
        className: 'bg-fit-relevant/12 text-fit-relevant ring-fit-relevant/30',
    },
    low: {
        label: 'Low match',
        className: 'bg-fit-low/12 text-fit-low ring-fit-low/30',
    },
};

/**
 * Category + score, e.g. "Strong fit · 86". Color is never the only signal.
 */
export function FitBadge({
    category,
    score,
    className,
}: {
    category: MatchCategory;
    score?: number;
    className?: string;
}) {
    const style = STYLES[category];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
                style.className,
                className,
            )}
        >
            {style.label}
            {score !== undefined && (
                <span className="tabular-nums opacity-80">· {score}</span>
            )}
        </span>
    );
}

export function NotScoredBadge() {
    return (
        <span className="text-xs whitespace-nowrap text-muted-foreground">
            Not scored
        </span>
    );
}

/**
 * Horizontal 0–100 bar tinted by category.
 */
export function ScoreBar({
    score,
    category,
}: {
    score: number;
    category: MatchCategory;
}) {
    const color = {
        strong: 'bg-fit-strong',
        relevant: 'bg-fit-relevant',
        low: 'bg-fit-low',
    }[category];

    return (
        <div
            className="h-2 w-full overflow-hidden rounded-full bg-muted"
            role="meter"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={score}
            aria-label="Fit score"
        >
            <div
                className={cn('h-full rounded-full transition-all', color)}
                style={{ width: `${score}%` }}
            />
        </div>
    );
}

const RING_COLORS: Record<MatchCategory, string> = {
    strong: 'text-fit-strong',
    relevant: 'text-fit-relevant',
    low: 'text-fit-low',
};

/**
 * Compact circular score for lists. The number stays readable, so the
 * color is a hint, not the message.
 */
export function ScoreRing({
    score,
    category,
    size = 40,
    className,
}: {
    score: number;
    category: MatchCategory;
    size?: number;
    className?: string;
}) {
    const stroke = size >= 56 ? 5 : 3.5;
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const filled = (Math.max(0, Math.min(100, score)) / 100) * circumference;

    return (
        <span
            className={cn(
                'relative inline-flex shrink-0 items-center justify-center',
                RING_COLORS[category],
                className,
            )}
            style={{ width: size, height: size }}
            role="meter"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={score}
            aria-label={`Fit score ${score} of 100, ${STYLES[category].label}`}
        >
            <svg
                width={size}
                height={size}
                viewBox={`0 0 ${size} ${size}`}
                className="-rotate-90"
                aria-hidden
            >
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeOpacity={0.15}
                    strokeWidth={stroke}
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={stroke}
                    strokeLinecap="round"
                    strokeDasharray={`${filled} ${circumference}`}
                    className="transition-[stroke-dasharray] duration-500 ease-out"
                />
            </svg>
            <span
                className={cn(
                    'absolute font-bold text-foreground tabular-nums',
                    size >= 56 ? 'text-lg' : 'text-xs',
                )}
            >
                {score}
            </span>
        </span>
    );
}
