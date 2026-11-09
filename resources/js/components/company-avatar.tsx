import { cn } from '@/lib/utils';

const TONES = [
    'bg-blue-500/12 text-blue-700 dark:text-blue-300',
    'bg-violet-500/12 text-violet-700 dark:text-violet-300',
    'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
    'bg-amber-500/14 text-amber-800 dark:text-amber-300',
    'bg-rose-500/12 text-rose-700 dark:text-rose-300',
    'bg-cyan-500/12 text-cyan-700 dark:text-cyan-300',
    'bg-fuchsia-500/12 text-fuchsia-700 dark:text-fuchsia-300',
];

function initials(name: string): string {
    const words = name
        .replace(/[^\p{L}\p{N}\s]/gu, ' ')
        .split(/\s+/)
        .filter(Boolean);

    if (words.length === 0) {
        return '?';
    }

    return (
        words.length === 1 ? words[0].slice(0, 2) : words[0][0] + words[1][0]
    ).toUpperCase();
}

/** Same company, same color: a stable hash of the name picks the tone. */
function toneFor(name: string): string {
    let hash = 0;

    for (const char of name) {
        hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
    }

    return TONES[hash % TONES.length];
}

/**
 * Job boards don't ship reliable logos, so companies get a colored monogram.
 */
export function CompanyAvatar({
    name,
    size = 'md',
    className,
}: {
    name: string;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-lg font-semibold tracking-tight',
                {
                    sm: 'size-8 text-xs',
                    md: 'size-10 text-sm',
                    lg: 'size-14 rounded-xl text-lg',
                }[size],
                toneFor(name),
                className,
            )}
        >
            {initials(name)}
        </span>
    );
}
