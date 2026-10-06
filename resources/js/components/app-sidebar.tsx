import { Link, usePage } from '@inertiajs/react';
import { Inbox, LayoutGrid, Settings, Code2 } from 'lucide-react';
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
import type { NavItem } from '@/types';

/**
 * Sidebar chrome (Agentic Application Shell Refactoring, session 13-09).
 *
 * When `currentSpace` is shared (we're on /spaces/{slug}/*), the nav is
 * scoped to that Space with Dashboard, Inbox, Embed, and Settings. When
 * no Space is active, the sidebar shows a single Dashboard pointing at
 * the Spaces index.
 */
export function AppSidebar() {
    const { currentSpace } = usePage().props;

    const mainNavItems: NavItem[] = currentSpace
        ? [
              {
                  title: 'Dashboard',
                  href: `/spaces/${currentSpace.slug}/dashboard`,
                  icon: LayoutGrid,
              },
              {
                  title: 'Inbox',
                  href: `/spaces/${currentSpace.slug}/inbox`,
                  icon: Inbox,
              },
              {
                  title: 'Embed',
                  href: `/spaces/${currentSpace.slug}/embed`,
                  icon: Code2,
              },
              {
                  title: 'Settings',
                  href: `/spaces/${currentSpace.slug}/settings`,
                  icon: Settings,
              },
          ]
        : [
              {
                  title: 'Dashboard',
                  href: '/spaces',
                  icon: LayoutGrid,
              },
          ];

    const logoHref = currentSpace
        ? `/spaces/${currentSpace.slug}/dashboard`
        : '/spaces';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={logoHref} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
