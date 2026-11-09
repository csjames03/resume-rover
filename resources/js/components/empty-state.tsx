import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
}: {
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed bg-muted/30 px-6 py-14 text-center">
            <span className="mb-4 flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary ring-8 ring-primary/5">
                <Icon className="size-6" aria-hidden />
            </span>
            <h3 className="font-semibold">{title}</h3>
            {description && (
                <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
