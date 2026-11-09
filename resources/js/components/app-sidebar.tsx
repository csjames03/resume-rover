import { Link } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    FileText,
    KanbanSquare,
    LayoutGrid,
    Mail,
    Rss,
    SlidersHorizontal,
    Sparkles,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { show as aiProviders } from '@/routes/ai-providers';
import { index as applications } from '@/routes/applications';
import { show as emailConnection } from '@/routes/email-connection';
import { index as jobs } from '@/routes/jobs';
import { edit as preferences } from '@/routes/preferences';
import { show as resume } from '@/routes/resume';
import { index as sources } from '@/routes/sources';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Jobs', href: jobs(), icon: BriefcaseBusiness },
    { title: 'Applications', href: applications(), icon: KanbanSquare },
    { title: 'Résumé', href: resume(), icon: FileText },
];

const setupNavItems: NavItem[] = [
    { title: 'Job sources', href: sources(), icon: Rss },
    { title: 'Preferences', href: preferences(), icon: SlidersHorizontal },
    { title: 'Email', href: emailConnection(), icon: Mail },
    { title: 'AI providers', href: aiProviders(), icon: Sparkles },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain label="Job search" items={mainNavItems} />
                <NavMain label="Setup" items={setupNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
