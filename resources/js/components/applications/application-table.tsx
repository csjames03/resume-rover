import { Link } from '@inertiajs/react';
import { CompanyAvatar } from '@/components/company-avatar';
import { OptionSelect } from '@/components/option-select';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, timeAgo } from '@/lib/format';
import { show as showJob } from '@/routes/jobs';
import type { Application, ApplicationStatus, Option } from '@/types';

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

export function ApplicationTable({
    applications,
    statuses,
    onStatusChange,
}: {
    applications: Application[];
    statuses: Option[];
    onStatusChange: (
        application: Application,
        status: ApplicationStatus,
    ) => void;
}) {
    const sorted = [...applications].sort(
        (a, b) => ORDER.indexOf(a.status) - ORDER.indexOf(b.status),
    );

    return (
        <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow className="bg-muted/40 hover:bg-muted/40">
                        <TableHead className="pl-4">Role</TableHead>
                        <TableHead className="w-44">Status</TableHead>
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
                            <TableCell className="py-3 pl-4">
                                <div className="flex items-center gap-3">
                                    {application.posting && (
                                        <CompanyAvatar
                                            name={application.posting.company}
                                            size="sm"
                                            className="hidden sm:inline-flex"
                                        />
                                    )}
                                    <div className="min-w-0">
                                        {application.posting && (
                                            <Link
                                                href={showJob(
                                                    application.posting.id,
                                                )}
                                                className="font-semibold hover:text-primary"
                                            >
                                                {application.posting.title}
                                            </Link>
                                        )}
                                        <div className="text-sm text-muted-foreground">
                                            {application.posting?.company}
                                            {application.posting?.closed_at && (
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
                                        status &&
                                        onStatusChange(
                                            application,
                                            status as ApplicationStatus,
                                        )
                                    }
                                    className="w-40"
                                />
                            </TableCell>
                            <TableCell className="hidden text-sm md:table-cell">
                                {application.next_action ? (
                                    <>
                                        <div>{application.next_action}</div>
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
    );
}
