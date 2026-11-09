import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground shadow-sm">
                <AppLogoIcon className="size-5 fill-current" />
            </div>
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
