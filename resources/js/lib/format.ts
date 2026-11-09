const dateFormatter = new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
});

const monthFormatter = new Intl.DateTimeFormat(undefined, {
    month: 'short',
    year: 'numeric',
});

const relativeFormatter = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

export function formatDate(value: string | null | undefined): string {
    return value ? dateFormatter.format(new Date(value)) : '—';
}

export function formatMonth(value: string | null | undefined): string {
    return value ? monthFormatter.format(new Date(value)) : '';
}

export function formatDateRange(
    start: string | null,
    end: string | null,
    isCurrent: boolean,
): string {
    const from = formatMonth(start);
    const to = isCurrent ? 'Present' : formatMonth(end);

    return [from, to].filter(Boolean).join(' – ');
}

const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31_536_000],
    ['month', 2_592_000],
    ['week', 604_800],
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
];

export function timeAgo(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const seconds = (new Date(value).getTime() - Date.now()) / 1000;

    for (const [unit, size] of UNITS) {
        if (Math.abs(seconds) >= size) {
            return relativeFormatter.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}

export function formatBytes(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.round(bytes / 1024)} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function formatMinutes(minutes: number): string {
    return minutes % 60 === 0 ? `${minutes / 60} h` : `${minutes} min`;
}
