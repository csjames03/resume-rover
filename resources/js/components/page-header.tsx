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
        // Wraps instead of squeezing: when the actions don't fit next to the
        // title, they drop below it rather than crushing the title column.
        <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
            <div className="min-w-0 flex-[1_1_20rem] space-y-1.5">
                {eyebrow && (
                    <p className="text-xs font-semibold tracking-wider text-primary uppercase">
                        {eyebrow}
                    </p>
                )}
                <h1 className="text-2xl font-bold tracking-tight md:text-[1.75rem]">
                    {title}
                </h1>
                {description && (
                    <p className="max-w-2xl text-sm text-pretty text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
