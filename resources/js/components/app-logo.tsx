import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            {/* Wrapped: the sidebar button shrinks direct <svg> children to 16px. */}
            <span className="flex size-8 shrink-0">
                <AppLogoIcon className="size-8 rounded-lg" />
            </span>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate text-[15px] leading-tight font-bold tracking-tight">
                    {name}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    Job search, organized
                </span>
            </div>
        </>
    );
}
