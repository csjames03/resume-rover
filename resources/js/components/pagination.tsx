import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

export function Pagination({
    meta,
    links,
}: Pick<Paginated<unknown>, 'meta' | 'links'>) {
    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <nav
            className="flex items-center justify-between gap-4 text-sm"
            aria-label="Pagination"
        >
            <p className="text-muted-foreground">
                {meta.from}–{meta.to} of {meta.total}
            </p>
            <div className="flex gap-2">
                <PageLink href={links.prev} label="Previous">
                    <ChevronLeft /> Previous
                </PageLink>
                <PageLink href={links.next} label="Next">
                    Next <ChevronRight />
                </PageLink>
            </div>
        </nav>
    );
}

function PageLink({
    href,
    label,
    children,
}: {
    href: string | null;
    label: string;
    children: React.ReactNode;
}) {
    if (!href) {
        return (
            <Button variant="outline" size="sm" disabled aria-label={label}>
                {children}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveScroll aria-label={label}>
                {children}
            </Link>
        </Button>
    );
}
