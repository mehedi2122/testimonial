import { Head, usePage } from '@inertiajs/react';
import { SpacePageShell } from '@/components/space-page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import type { PageProps } from '@/types';

type SpaceDashboardProps = PageProps & {
    space: {
        id: number;
        slug: string;
        name: string;
    };
};

type FlashMessages = {
    success?: string;
    error?: string;
};

type SharedPageProps = {
    flash?: FlashMessages;
};

export default function SpaceDashboard({ space }: SpaceDashboardProps) {
    const { flash } = usePage<SharedPageProps>().props;

    return (
        <SpacePageShell
            title={`${space.name} — Dashboard`}
            description="Live testimonial stats and recent activity will appear here."
        >
            <div className="flex flex-col gap-4">
                {flash?.success && (
                    <Alert>
                        <AlertTitle>Done</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}
                {flash?.error && (
                    <Alert variant="destructive">
                        <AlertTitle>Heads up</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}
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
            </div>
        </SpacePageShell>
    );
}
