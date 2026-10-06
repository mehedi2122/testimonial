import { Link, usePage } from '@inertiajs/react';
import { SpacePageShell } from '@/components/space-page-shell';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import type { PageProps } from '@/types';

type SpacesIndexProps = PageProps & {
    // The /spaces index is intentionally minimal: the actual Space list
    // and create form land in a future OpenSpec change.
};

export default function SpacesIndex(_: SpacesIndexProps) {
    const { auth } = usePage().props;
    const spaces = auth.user?.spaces ?? [];

    return (
        <SpacePageShell
            title="Your Spaces"
            description="Create a Space to start collecting testimonials, or open an existing one."
        >
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {spaces.length === 0 ? (
                    <div className="col-span-full flex aspect-video items-center justify-center rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        <span className="relative text-sm text-muted-foreground">
                            No spaces yet. The Space creation flow lands in a
                            future change.
                        </span>
                    </div>
                ) : (
                    spaces.map((space) => (
                        <Link
                            key={space.id}
                            href={`/spaces/${space.slug}/dashboard`}
                            className="flex aspect-video flex-col justify-between rounded-xl border border-sidebar-border/70 p-4 transition hover:border-sidebar-border hover:shadow-sm dark:border-sidebar-border"
                        >
                            <span className="text-base font-medium">
                                {space.name}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                /spaces/{space.slug}
                            </span>
                        </Link>
                    ))
                )}
            </div>
        </SpacePageShell>
    );
}
