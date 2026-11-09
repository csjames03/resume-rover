import { Head, Link, router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { KanbanSquare, Rows3 } from 'lucide-react';
import ApplicationController from '@/actions/App/Http/Controllers/ApplicationController';
import { ApplicationBoard } from '@/components/applications/application-board';
import { ApplicationTable } from '@/components/applications/application-table';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index } from '@/routes/applications';
import { index as jobsIndex } from '@/routes/jobs';
import type { Application, ApplicationStatus, Option } from '@/types';

type View = 'table' | 'board';

type Props = {
    applications: Application[];
    statuses: Option[];
    showingArchived: boolean;
    view: View;
};

const VIEWS: { value: View; label: string; icon: LucideIcon }[] = [
    { value: 'table', label: 'Table', icon: Rows3 },
    { value: 'board', label: 'Board', icon: KanbanSquare },
];

export default function ApplicationsIndex({
    applications,
    statuses,
    showingArchived,
    view,
}: Props) {
    const query = (changes: { archived?: boolean; view?: View }) => {
        const archived = changes.archived ?? showingArchived;
        const nextView = changes.view ?? view;

        return {
            ...(archived ? { archived: 1 } : {}),
            ...(nextView === 'board' ? { view: 'board' } : {}),
        };
    };

    const changeStatus = (
        application: Application,
        status: ApplicationStatus,
        onSettled?: () => void,
    ) => {
        router.patch(
            ApplicationController.update(application.id).url,
            { status },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['applications'],
                onFinish: onSettled,
            },
        );
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
                    description={
                        view === 'board'
                            ? 'Drag a card to another column to change its status.'
                            : "Every job you've saved or applied to, in one place."
                    }
                    actions={
                        <>
                            <nav
                                aria-label="View"
                                className="inline-flex rounded-lg border bg-muted/60 p-0.5"
                            >
                                {VIEWS.map((option) => (
                                    <Link
                                        key={option.value}
                                        href={index({
                                            query: query({
                                                view: option.value,
                                            }),
                                        })}
                                        preserveScroll
                                        aria-current={
                                            view === option.value
                                                ? 'page'
                                                : undefined
                                        }
                                        className={cn(
                                            'inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            view === option.value &&
                                                'bg-background text-foreground shadow-sm',
                                        )}
                                    >
                                        <option.icon
                                            className="size-4"
                                            aria-hidden
                                        />
                                        {option.label}
                                    </Link>
                                ))}
                            </nav>
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={index({
                                        query: query({
                                            archived: !showingArchived,
                                        }),
                                    })}
                                >
                                    {showingArchived
                                        ? 'Show active'
                                        : 'Show archived'}
                                </Link>
                            </Button>
                        </>
                    }
                />

                {applications.length === 0 ? (
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
                ) : view === 'board' ? (
                    <ApplicationBoard
                        applications={applications}
                        onStatusChange={changeStatus}
                    />
                ) : (
                    <ApplicationTable
                        applications={applications}
                        statuses={statuses}
                        onStatusChange={changeStatus}
                    />
                )}
            </div>
        </>
    );
}

ApplicationsIndex.layout = {
    breadcrumbs: [{ title: 'Applications', href: index() }],
};
