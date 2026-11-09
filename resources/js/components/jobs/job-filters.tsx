import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { OptionSelect } from '@/components/option-select';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/jobs';
import type { Option } from '@/types';

export type Filters = {
    q: string | null;
    category: string | null;
    remote: string | null;
    source: number | null;
    status: string | null;
    include_closed: boolean;
    sort: string;
};

export type FilterOptions = {
    categories: Option[];
    remote: Option[];
    statuses: Option[];
    sources: Option[];
};

const SORTS: Option[] = [
    { value: 'score', label: 'Best fit' },
    { value: 'posted', label: 'Newest posted' },
    { value: 'found', label: 'Recently found' },
];

/**
 * Filters live in the query string, so a filtered view can be bookmarked.
 */
export function JobFilters({
    filters,
    options,
}: {
    filters: Filters;
    options: FilterOptions;
}) {
    const [search, setSearch] = useState(filters.q ?? '');
    const firstRender = useRef(true);

    const apply = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };
        const query = Object.fromEntries(
            Object.entries(next).filter(
                ([, value]) =>
                    value !== null && value !== '' && value !== false,
            ),
        );

        router.get(index.url(), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['postings', 'filters'],
        });
    };

    // Debounce typing so every keystroke doesn't hit the server.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(() => apply({ q: search || null }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return (
        <div className="flex flex-wrap items-center gap-2 rounded-xl border bg-card p-2.5 shadow-sm">
            <div className="relative w-full sm:w-72">
                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search title or company"
                    className="h-9 border-transparent bg-muted/60 pl-8 shadow-none focus-visible:bg-background"
                    aria-label="Search jobs"
                />
            </div>
            <OptionSelect
                value={filters.category}
                options={options.categories}
                anyLabel="Any fit"
                onChange={(category) => apply({ category })}
            />
            <OptionSelect
                value={filters.remote}
                options={options.remote}
                anyLabel="Any arrangement"
                onChange={(remote) => apply({ remote })}
            />
            <OptionSelect
                value={filters.status}
                options={options.statuses}
                anyLabel="Any status"
                onChange={(status) => apply({ status })}
            />
            <OptionSelect
                value={filters.source?.toString() ?? null}
                options={options.sources}
                anyLabel="All sources"
                onChange={(source) =>
                    apply({ source: source ? Number(source) : null })
                }
            />
            <OptionSelect
                value={filters.sort}
                options={SORTS}
                onChange={(sort) => apply({ sort: sort ?? 'score' })}
            />
            <div className="flex items-center gap-2 px-2 sm:ml-auto">
                <Checkbox
                    id="include-closed"
                    checked={filters.include_closed}
                    onCheckedChange={(checked) =>
                        apply({ include_closed: checked === true })
                    }
                />
                <Label htmlFor="include-closed" className="text-sm font-normal">
                    Show closed
                </Label>
            </div>
        </div>
    );
}
