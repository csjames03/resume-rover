import { Breadcrumbs } from '@/components/breadcrumbs';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b bg-background/85 px-4 backdrop-blur-md md:rounded-t-xl md:px-6">
            <SidebarTrigger className="-ml-1.5" />
            <Separator orientation="vertical" className="mr-1 h-4!" />
            <Breadcrumbs breadcrumbs={breadcrumbs} />
        </header>
    );
}
