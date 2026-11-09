import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core';
import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { Link } from '@inertiajs/react';
import { CalendarClock, GripVertical } from 'lucide-react';
import { useState } from 'react';
import { CompanyAvatar } from '@/components/company-avatar';
import { STATUS_STYLES } from '@/components/status-badge';
import { formatDate, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show as showJob } from '@/routes/jobs';
import type { Application, ApplicationStatus } from '@/types';

// Left to right in the order a search moves; outcomes at the end.
const COLUMNS: ApplicationStatus[] = [
    'saved',
    'preparing',
    'applied',
    'interviewing',
    'offer',
    'rejected',
    'withdrawn',
];

/**
 * Kanban view of the pipeline. Dropping a card on another column changes its
 * status. The card moves at once and the server confirms it in the background.
 */
export function ApplicationBoard({
    applications,
    onStatusChange,
}: {
    applications: Application[];
    onStatusChange: (
        application: Application,
        status: ApplicationStatus,
        onSettled: () => void,
    ) => void;
}) {
    // Moves waiting for the server, so a dropped card doesn't jump back.
    const [moved, setMoved] = useState<Record<number, ApplicationStatus>>({});
    const [dragging, setDragging] = useState<Application | null>(null);

    const sensors = useSensors(
        // A small distance keeps plain clicks on the job link working.
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor),
    );

    const statusOf = (application: Application) =>
        moved[application.id] ?? application.status;

    const handleDragStart = ({ active }: DragStartEvent) => {
        setDragging(
            applications.find((a) => a.id === Number(active.id)) ?? null,
        );
    };

    const handleDragEnd = ({ active, over }: DragEndEvent) => {
        setDragging(null);

        const application = applications.find(
            (a) => a.id === Number(active.id),
        );
        const target = over?.id as ApplicationStatus | undefined;

        if (!application || !target || target === statusOf(application)) {
            return;
        }

        setMoved((current) => ({ ...current, [application.id]: target }));
        onStatusChange(application, target, () =>
            setMoved(({ [application.id]: _settled, ...rest }) => rest),
        );
    };

    return (
        <DndContext
            sensors={sensors}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onDragCancel={() => setDragging(null)}
            accessibility={{
                screenReaderInstructions: {
                    draggable:
                        'Press space or enter to pick up this application. Use the arrow keys to move it to another status, then press space or enter again to drop it. Press escape to cancel.',
                },
            }}
        >
            <div className="-mx-4 overflow-x-auto px-4 pb-4 md:-mx-8 md:px-8">
                <div className="grid auto-cols-[minmax(17rem,1fr)] grid-flow-col gap-4">
                    {COLUMNS.map((status) => (
                        <BoardColumn
                            key={status}
                            status={status}
                            applications={applications.filter(
                                (a) => statusOf(a) === status,
                            )}
                        />
                    ))}
                </div>
            </div>

            <DragOverlay dropAnimation={{ duration: 180, easing: 'ease-out' }}>
                {dragging && <CardBody application={dragging} lifted />}
            </DragOverlay>
        </DndContext>
    );
}

function BoardColumn({
    status,
    applications,
}: {
    status: ApplicationStatus;
    applications: Application[];
}) {
    const { setNodeRef, isOver } = useDroppable({ id: status });
    const style = STATUS_STYLES[status];

    return (
        <section
            aria-label={`${style.label}, ${applications.length} applications`}
            className="flex min-h-[24rem] flex-col rounded-xl bg-muted/50 ring-1 ring-border/60"
        >
            <header className="flex items-center gap-2 px-3 pt-3 pb-2">
                <span
                    className={cn('size-2 rounded-full', style.dot)}
                    aria-hidden
                />
                <h2 className="text-sm font-semibold">{style.label}</h2>
                <span className="ml-auto rounded-full bg-background px-2 py-0.5 text-xs font-medium text-muted-foreground tabular-nums ring-1 ring-border">
                    {applications.length}
                </span>
            </header>

            <div
                ref={setNodeRef}
                className={cn(
                    'm-1.5 mt-0 flex flex-1 flex-col gap-2 rounded-lg p-1.5 transition-colors duration-150',
                    isOver && 'bg-primary/8 ring-2 ring-primary/40 ring-inset',
                )}
            >
                {applications.map((application) => (
                    <BoardCard key={application.id} application={application} />
                ))}
                {applications.length === 0 && (
                    <p className="flex flex-1 items-center justify-center rounded-lg border border-dashed px-3 py-8 text-center text-xs text-muted-foreground">
                        Drop a card here
                    </p>
                )}
            </div>
        </section>
    );
}

function BoardCard({ application }: { application: Application }) {
    const {
        setNodeRef,
        setActivatorNodeRef,
        listeners,
        attributes,
        isDragging,
    } = useDraggable({ id: application.id });

    return (
        <div
            ref={setNodeRef}
            // Pointer drags start anywhere on the card; the handle is the keyboard entry point.
            {...listeners}
            className={cn(
                'touch-none transition-opacity',
                isDragging && 'opacity-40',
            )}
        >
            <CardBody
                application={application}
                handle={
                    <button
                        ref={setActivatorNodeRef}
                        type="button"
                        {...attributes}
                        aria-label={`Move ${application.posting?.title ?? 'application'}`}
                        className="-mr-1.5 flex size-7 shrink-0 cursor-grab items-center justify-center rounded-md text-muted-foreground opacity-60 transition-opacity group-hover:opacity-100 hover:bg-muted focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing"
                    >
                        <GripVertical className="size-4" aria-hidden />
                    </button>
                }
            />
        </div>
    );
}

function CardBody({
    application,
    handle,
    lifted = false,
}: {
    application: Application;
    handle?: React.ReactNode;
    lifted?: boolean;
}) {
    const posting = application.posting;

    return (
        <article
            className={cn(
                'group rounded-lg border bg-card p-3 shadow-xs transition-shadow hover:shadow-md',
                lifted &&
                    'rotate-2 cursor-grabbing shadow-xl ring-2 ring-primary/30',
            )}
        >
            <div className="flex items-start gap-2.5">
                {posting && <CompanyAvatar name={posting.company} size="sm" />}
                <div className="min-w-0 flex-1">
                    {posting ? (
                        <Link
                            href={showJob(posting.id)}
                            className="line-clamp-2 text-sm leading-snug font-semibold hover:text-primary"
                        >
                            {posting.title}
                        </Link>
                    ) : (
                        <p className="text-sm font-semibold">Removed posting</p>
                    )}
                    <p className="truncate text-xs text-muted-foreground">
                        {posting?.company}
                    </p>
                </div>
                {handle}
            </div>

            {application.next_action && (
                <p className="mt-3 flex items-center gap-1.5 rounded-md bg-primary/8 px-2 py-1 text-xs text-primary">
                    <CalendarClock className="size-3.5 shrink-0" aria-hidden />
                    <span className="truncate">
                        {application.next_action}
                        {application.next_action_at &&
                            ` · ${formatDate(application.next_action_at)}`}
                    </span>
                </p>
            )}

            <div className="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                <span>Updated {timeAgo(application.updated_at)}</span>
                {posting?.closed_at && (
                    <span className="rounded-full border px-1.5 py-0.5">
                        Closed
                    </span>
                )}
            </div>
        </article>
    );
}
