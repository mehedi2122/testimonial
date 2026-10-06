import { Link, usePage } from '@inertiajs/react';
import { Inbox, LayoutGrid, Plus, Settings, Code2 } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Button } from '@/components/ui/button';
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
 * no Space is active, the sidebar shows a Dashboard link plus a Create-
 * Space CTA so a 0-Space account still has a clear next action.
 */
export function AppSidebar() {
    const { auth, currentSpace } = usePage().props;
    const spaceCount = auth.user?.spaces?.length ?? 0;

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
                {!currentSpace && spaceCount === 0 && (
                    <div className="px-3 pb-3">
                        <Button
                            asChild
                            className="w-full justify-start"
                            size="sm"
                        >
                            <Link href="/spaces/create" prefetch>
                                <Plus className="size-4" />
                                Create Space
                            </Link>
                        </Button>
                    </div>
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
