import { SpacePageShell } from '@/components/space-page-shell';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import type { PageProps } from '@/types';

type SpaceDashboardProps = PageProps & {
    space: {
        id: number;
        slug: string;
        name: string;
    };
};

export default function SpaceDashboard({ space }: SpaceDashboardProps) {
    return (
        <SpacePageShell
            title={`${space.name} — Dashboard`}
            description="Live testimonial stats and recent activity will appear here."
        >
            <div className="grid gap-4 md:grid-cols-3">
                {[0, 1, 2].map((i) => (
                    <div
                        key={i}
                        className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                ))}
            </div>
            <div className="relative min-h-[60vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
            </div>
        </SpacePageShell>
    );
}
