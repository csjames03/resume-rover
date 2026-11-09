import { Head, Link, router } from '@inertiajs/react';
import { KanbanSquare } from 'lucide-react';
import ApplicationController from '@/actions/App/Http/Controllers/ApplicationController';
import { CompanyAvatar } from '@/components/company-avatar';
import { EmptyState } from '@/components/empty-state';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
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
import { index } from '@/routes/applications';
import { index as jobsIndex, show as showJob } from '@/routes/jobs';
import type { Application, ApplicationStatus, Option } from '@/types';

type Props = {
    applications: Application[];
    statuses: Option[];
    showingArchived: boolean;
};

// Open stages first, closed outcomes last.
const ORDER: ApplicationStatus[] = [
    'interviewing',
    'offer',
    'applied',
    'preparing',
    'saved',
    'rejected',
    'withdrawn',
];

export default function ApplicationsIndex({
    applications,
    statuses,
    showingArchived,
}: Props) {
    const sorted = [...applications].sort(
        (a, b) => ORDER.indexOf(a.status) - ORDER.indexOf(b.status),
    );

    const changeStatus = (application: Application, status: string | null) => {
        if (status) {
            router.patch(
                ApplicationController.update(application.id).url,
                { status },
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title="Applications" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 p-4 md:p-8">
                <PageHeader
                    title={
                        showingArchived
                            ? 'Archived applications'
                            : 'Applications'
                    }
                    description="Every job you've saved or applied to, in one place."
                    actions={
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={index({
                                    query: showingArchived
                                        ? {}
                                        : { archived: 1 },
                                })}
                            >
                                {showingArchived
                                    ? 'Show active'
                                    : 'Show archived'}
                            </Link>
                        </Button>
                    }
                />

                {sorted.length === 0 ? (
                    <EmptyState
                        icon={KanbanSquare}
                        title={
                            showingArchived
                                ? 'Nothing archived'
                                : 'No applications yet'
                        }
                        description="Save a job from its detail page to start tracking it."
                        action={
                            <Button asChild>
                                <Link href={jobsIndex()}>Browse jobs</Link>
                            </Button>
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/40 hover:bg-muted/40">
                                    <TableHead className="pl-4">Role</TableHead>
                                    <TableHead className="w-44">
                                        Status
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Next action
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Applied
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Updated
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sorted.map((application) => (
                                    <TableRow key={application.id}>
                                        <TableCell className="py-3">
                                            <div className="flex items-center gap-3">
                                                {application.posting && (
                                                    <CompanyAvatar
                                                        name={
                                                            application.posting
                                                                .company
                                                        }
                                                        size="sm"
                                                        className="hidden sm:inline-flex"
                                                    />
                                                )}
                                                <div className="min-w-0">
                                                    {application.posting && (
                                                        <Link
                                                            href={showJob(
                                                                application
                                                                    .posting.id,
                                                            )}
                                                            className="font-semibold hover:text-primary"
                                                        >
                                                            {
                                                                application
                                                                    .posting
                                                                    .title
                                                            }
                                                        </Link>
                                                    )}
                                                    <div className="text-sm text-muted-foreground">
                                                        {
                                                            application.posting
                                                                ?.company
                                                        }
                                                        {application.posting
                                                            ?.closed_at && (
                                                            <Badge
                                                                variant="outline"
                                                                className="ml-2"
                                                            >
                                                                Posting closed
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <OptionSelect
                                                value={application.status}
                                                options={statuses}
                                                onChange={(status) =>
                                                    changeStatus(
                                                        application,
                                                        status,
                                                    )
                                                }
                                                className="w-40"
                                            />
                                        </TableCell>
                                        <TableCell className="hidden text-sm md:table-cell">
                                            {application.next_action ? (
                                                <>
                                                    <div>
                                                        {
                                                            application.next_action
                                                        }
                                                    </div>
                                                    <div className="text-muted-foreground">
                                                        {formatDate(
                                                            application.next_action_at,
                                                        )}
                                                    </div>
                                                </>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="hidden text-sm text-muted-foreground lg:table-cell">
                                            {formatDate(application.applied_at)}
                                        </TableCell>
                                        <TableCell className="hidden text-sm text-muted-foreground lg:table-cell">
                                            {timeAgo(application.updated_at)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

ApplicationsIndex.layout = {
    breadcrumbs: [{ title: 'Applications', href: index() }],
};
