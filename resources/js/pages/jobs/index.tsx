import { Head, Link } from '@inertiajs/react';
import { BriefcaseBusiness, FileUp } from 'lucide-react';
import { CompanyAvatar } from '@/components/company-avatar';
import { EmptyState } from '@/components/empty-state';
import { FitBadge, NotScoredBadge, ScoreRing } from '@/components/fit-badge';
import type { FilterOptions, Filters } from '@/components/jobs/job-filters';
import { JobFilters } from '@/components/jobs/job-filters';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, timeAgo } from '@/lib/format';
import { index, show } from '@/routes/jobs';
import { show as showResume } from '@/routes/resume';
import type { JobPosting, Paginated } from '@/types';

type Props = {
    postings: Paginated<JobPosting>;
    filters: Filters;
    options: FilterOptions;
    hasResume: boolean;
};

export default function JobsIndex({
    postings,
    filters,
    options,
    hasResume,
}: Props) {
    return (
        <>
            <Head title="Jobs" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 p-4 md:p-8">
                <PageHeader
                    title="Jobs"
                    description={`${postings.meta.total.toLocaleString()} roles from your sources, scored against your résumé.`}
                />

                {!hasResume && (
                    <Alert>
                        <FileUp />
                        <AlertTitle>Jobs aren't scored yet</AlertTitle>
                        <AlertDescription>
                            <span>
                                <Link href={showResume()} className="underline">
                                    Upload your résumé
                                </Link>{' '}
                                to see how well each role fits.
                            </span>
                        </AlertDescription>
                    </Alert>
                )}

                <JobFilters filters={filters} options={options} />

                {postings.data.length === 0 ? (
                    <EmptyState
                        icon={BriefcaseBusiness}
                        title="No jobs match these filters"
                        description="Loosen a filter, or add more sources on the Sources page."
                        action={
                            <Button variant="outline" asChild>
                                <Link href={index()}>Clear filters</Link>
                            </Button>
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/40 hover:bg-muted/40">
                                    <TableHead className="pl-4">Fit</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Location
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Posted
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Found
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {postings.data.map((posting) => (
                                    <JobRow
                                        key={posting.id}
                                        posting={posting}
                                    />
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination meta={postings.meta} links={postings.links} />
            </div>
        </>
    );
}

function JobRow({ posting }: { posting: JobPosting }) {
    return (
        // The title link stretches over the whole row (after:inset-0), so the row needs `relative`.
        <TableRow className="group relative">
            <TableCell className="w-40 py-3 pl-4">
                {posting.match ? (
                    <div className="flex items-center gap-2.5">
                        <ScoreRing
                            score={posting.match.score}
                            category={posting.match.category}
                            size={36}
                        />
                        <FitBadge
                            category={posting.match.category}
                            className="hidden xl:inline-flex"
                        />
                    </div>
                ) : (
                    <NotScoredBadge />
                )}
            </TableCell>
            <TableCell className="max-w-md py-3">
                <div className="flex items-center gap-3">
                    <CompanyAvatar
                        name={posting.company}
                        size="sm"
                        className="hidden sm:inline-flex"
                    />
                    <div className="min-w-0">
                        <Link
                            href={show(posting.id)}
                            className="block truncate font-semibold group-hover:text-primary after:absolute after:inset-0 focus-visible:outline-none"
                            prefetch
                        >
                            {posting.title}
                        </Link>
                        <span className="block truncate text-sm text-muted-foreground">
                            {posting.company}
                            {posting.source?.attribution &&
                                ` · ${posting.source.attribution}`}
                            {posting.closed_at && (
                                <Badge variant="outline" className="ml-2">
                                    Closed
                                </Badge>
                            )}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell className="hidden text-sm md:table-cell">
                <div className="font-medium">{posting.remote_label}</div>
                <div className="max-w-56 truncate text-muted-foreground">
                    {posting.location}
                </div>
            </TableCell>
            <TableCell className="hidden text-sm whitespace-nowrap text-muted-foreground lg:table-cell">
                {formatDate(posting.posted_at)}
            </TableCell>
            <TableCell className="hidden text-sm whitespace-nowrap text-muted-foreground lg:table-cell">
                {timeAgo(posting.first_seen_at)}
            </TableCell>
            <TableCell>
                {posting.application ? (
                    <StatusBadge status={posting.application.status} />
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                )}
            </TableCell>
        </TableRow>
    );
}

JobsIndex.layout = {
    breadcrumbs: [{ title: 'Jobs', href: index() }],
};
