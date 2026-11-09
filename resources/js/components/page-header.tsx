import type { ReactNode } from 'react';

export function PageHeader({
    title,
    description,
    actions,
    eyebrow,
}: {
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    eyebrow?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="min-w-0 space-y-1.5">
                {eyebrow && (
                    <p className="text-xs font-semibold tracking-wider text-primary uppercase">
                        {eyebrow}
                    </p>
                )}
                <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                {description && (
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
