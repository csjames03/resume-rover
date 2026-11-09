import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, RefreshCw, Rss, Trash2 } from 'lucide-react';
import { useState } from 'react';
import JobSourceController from '@/actions/App/Http/Controllers/JobSourceController';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMinutes, timeAgo } from '@/lib/format';
import { index } from '@/routes/sources';
import type { JobSource } from '@/types';

type Driver = {
    value: string;
    label: string;
    hint: string;
    requires_identifier: boolean;
    minimum_interval: number;
};

type Props = {
    sources: JobSource[];
    drivers: Driver[];
};

export default function SourcesIndex({ sources, drivers }: Props) {
    const [editing, setEditing] = useState<JobSource | 'new' | null>(null);

    return (
        <>
            <Head title="Sources" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 p-4 md:p-8">
                <PageHeader
                    title="Job sources"
                    description="Official, public job feeds only: no scraping, no CAPTCHA tricks. Each source keeps its own fetch limits."
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus /> Add source
                        </Button>
                    }
                />

                {sources.length === 0 ? (
                    <EmptyState
                        icon={Rss}
                        title="No sources yet"
                        description="Add a Greenhouse board, a Lever company page, or a Remotive search."
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Source</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Open jobs
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Refresh
                                    </TableHead>
                                    <TableHead>Last fetch</TableHead>
                                    <TableHead className="w-0" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sources.map((source) => (
                                    <SourceRow
                                        key={source.id}
                                        source={source}
                                        onEdit={() => setEditing(source)}
                                    />
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            <SourceDialog
                key={editing === 'new' ? 'new' : editing?.id}
                source={editing === 'new' ? null : editing}
                open={editing !== null}
                drivers={drivers}
                onClose={() => setEditing(null)}
            />
        </>
    );
}

function SourceRow({
    source,
    onEdit,
}: {
    source: JobSource;
    onEdit: () => void;
}) {
    const fetchNow = () =>
        router.post(
            JobSourceController.fetch(source.id).url,
            {},
            { preserveScroll: true },
        );
    const remove = () => {
        if (
            confirm(
                `Remove ${source.name}? Sources with postings are disabled instead, so your history is kept.`,
            )
        ) {
            router.delete(JobSourceController.destroy(source.id).url, {
                preserveScroll: true,
            });
        }
    };

    return (
        <TableRow className={source.is_enabled ? undefined : 'opacity-60'}>
            <TableCell>
                <div className="flex items-center gap-2 font-medium">
                    {source.name}
                    {!source.is_enabled && (
                        <Badge variant="outline">Disabled</Badge>
                    )}
                </div>
                <div className="text-sm text-muted-foreground">
                    {source.driver_label}
                    {source.identifier && ` · ${source.identifier}`}
                </div>
                {source.title_filter && (
                    <div className="text-xs text-muted-foreground">
                        Titles with: {source.title_filter}
                    </div>
                )}
            </TableCell>
            <TableCell className="hidden tabular-nums md:table-cell">
                {source.open_postings_count ?? 0}
            </TableCell>
            <TableCell className="hidden text-sm md:table-cell">
                every {formatMinutes(source.effective_interval_minutes)}
            </TableCell>
            <TableCell className="text-sm">
                {source.last_status === 'failed' ? (
                    <span
                        className="text-destructive"
                        title={source.last_error ?? undefined}
                    >
                        Failed {timeAgo(source.last_fetched_at)}
                    </span>
                ) : source.last_fetched_at ? (
                    <span className="text-muted-foreground">
                        {timeAgo(source.last_fetched_at)} ·{' '}
                        {source.last_fetched_count ?? 0} jobs
                    </span>
                ) : (
                    <span className="text-muted-foreground">Never</span>
                )}
            </TableCell>
            <TableCell>
                <div className="flex gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={fetchNow}
                        disabled={!source.is_enabled}
                        aria-label={`Fetch ${source.name} now`}
                    >
                        <RefreshCw />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={onEdit}
                        aria-label={`Edit ${source.name}`}
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={remove}
                        aria-label={`Remove ${source.name}`}
                    >
                        <Trash2 />
                    </Button>
                </div>
            </TableCell>
        </TableRow>
    );
}

function SourceDialog({
    source,
    open,
    drivers,
    onClose,
}: {
    source: JobSource | null;
    open: boolean;
    drivers: Driver[];
    onClose: () => void;
}) {
    const form = useForm({
        name: source?.name ?? '',
        driver: source?.driver ?? drivers[0]?.value ?? 'greenhouse',
        identifier: source?.identifier ?? '',
        title_filter: source?.title_filter ?? '',
        fetch_interval_minutes: source?.fetch_interval_minutes ?? 360,
        is_enabled: source?.is_enabled ?? true,
    });

    const driver = drivers.find((d) => d.value === form.data.driver);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.submit(
            source
                ? JobSourceController.update(source.id)
                : JobSourceController.store(),
            {
                preserveScroll: true,
                onSuccess: onClose,
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {source
                                ? `Edit ${source.name}`
                                : 'Add a job source'}
                        </DialogTitle>
                        <DialogDescription>
                            Only add sources whose terms allow this kind of use.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-1.5">
                        <Label htmlFor="source-driver">Type</Label>
                        <OptionSelect
                            id="source-driver"
                            value={form.data.driver}
                            options={drivers}
                            onChange={(value) =>
                                form.setData('driver', value ?? 'greenhouse')
                            }
                            className="w-full"
                        />
                        <InputError message={form.errors.driver} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="source-name">Name</Label>
                        <Input
                            id="source-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="Company name"
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="source-identifier">
                            {driver?.requires_identifier
                                ? 'Company'
                                : 'Keywords (optional)'}
                        </Label>
                        <Input
                            id="source-identifier"
                            value={form.data.identifier}
                            onChange={(e) =>
                                form.setData('identifier', e.target.value)
                            }
                        />
                        {driver && (
                            <p className="text-xs text-muted-foreground">
                                {driver.hint}
                            </p>
                        )}
                        <InputError message={form.errors.identifier} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="source-title-filter">
                            Only keep titles containing (optional)
                        </Label>
                        <Input
                            id="source-title-filter"
                            value={form.data.title_filter}
                            onChange={(e) =>
                                form.setData('title_filter', e.target.value)
                            }
                            placeholder="developer, engineer, frontend"
                        />
                        <p className="text-xs text-muted-foreground">
                            Comma-separated. Leave empty to keep every job.
                        </p>
                        <InputError message={form.errors.title_filter} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="source-interval">
                            Refresh every (minutes)
                        </Label>
                        <Input
                            id="source-interval"
                            type="number"
                            min={60}
                            step={30}
                            value={form.data.fetch_interval_minutes}
                            onChange={(e) =>
                                form.setData(
                                    'fetch_interval_minutes',
                                    Number(e.target.value),
                                )
                            }
                        />
                        {driver && driver.minimum_interval > 60 && (
                            <p className="text-xs text-muted-foreground">
                                This source's terms allow at most one fetch
                                every {formatMinutes(driver.minimum_interval)}.
                            </p>
                        )}
                        <InputError
                            message={form.errors.fetch_interval_minutes}
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="source-enabled"
                            checked={form.data.is_enabled}
                            onCheckedChange={(checked) =>
                                form.setData('is_enabled', checked === true)
                            }
                        />
                        <Label htmlFor="source-enabled">Enabled</Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {source ? 'Save' : 'Add and fetch'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

SourcesIndex.layout = {
    breadcrumbs: [{ title: 'Sources', href: index() }],
};
