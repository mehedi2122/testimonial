import { Link, usePage } from '@inertiajs/react';
import { ChevronsUpDown, Plus } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type SpaceOption = {
    id: number;
    slug: string;
    name: string;
};

/**
 * Header dropdown listing the authenticated user's Spaces. When a Space is
 * active (`usePage().props.currentSpace`), the trigger shows its name; when
 * none is active, the trigger reads "Switch space".
 */
export function SpaceSwitcher() {
    const { auth, currentSpace } = usePage().props;
    const spaces: SpaceOption[] = auth.user?.spaces ?? [];
    const active = currentSpace ?? null;

    const triggerLabel = active?.name ?? 'Switch space';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="inline-flex items-center gap-2 rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground">
                <span className="max-w-[10rem] truncate">{triggerLabel}</span>
                <ChevronsUpDown className="size-4 opacity-60" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-56">
                <DropdownMenuLabel>Spaces</DropdownMenuLabel>
                {spaces.length === 0 ? (
                    <DropdownMenuItem disabled>No spaces yet</DropdownMenuItem>
                ) : (
                    spaces.map((space) => (
                        <DropdownMenuItem key={space.id} asChild>
                            <Link
                                href={`/spaces/${space.slug}/dashboard`}
                                className="cursor-pointer"
                            >
                                <span className="truncate">{space.name}</span>
                            </Link>
                        </DropdownMenuItem>
                    ))
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href="/spaces" className="cursor-pointer">
                        All spaces
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href="/spaces" className="cursor-pointer">
                        <Plus className="mr-2 size-4" />
                        <span>New space</span>
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
