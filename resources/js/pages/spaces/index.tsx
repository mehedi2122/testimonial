import { Link, router, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { SpacePageShell } from '@/components/space-page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import type { PageProps } from '@/types';

type SpaceRow = {
    id: number;
    name: string;
    slug: string;
    theme: string;
    rating_enabled: boolean;
    created_at: string | null;
};

type SpacesIndexProps = PageProps & {
    spaces: SpaceRow[];
};

type FlashMessages = {
    success?: string;
    error?: string;
};

type SharedPageProps = {
    flash?: FlashMessages;
};

/**
 * Spaces index (OpenSpec change: space-crud). Lists the authenticated
 * user's live Spaces with a per-row delete confirmation. Empty state
 * shows a Create-Space CTA. Session flash (success / error) is rendered
 * at the top so the controller's `with('success' / 'error')` messages are
 * surfaced without a toast provider.
 */
export default function SpacesIndex({ spaces }: SpacesIndexProps) {
    const { flash } = usePage<SharedPageProps>().props;
    const [pendingDelete, setPendingDelete] = useState<SpaceRow | null>(null);

    const confirmDelete = (): void => {
        if (!pendingDelete) {
            return;
        }
        router.delete(`/spaces/${pendingDelete.slug}`, {
            preserveScroll: true,
            onFinish: () => setPendingDelete(null),
        });
    };

    return (
        <SpacePageShell
            title="Your Spaces"
            description="Create a Space to start collecting testimonials, or open an existing one."
        >
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">
                        {spaces.length}{' '}
                        {spaces.length === 1 ? 'Space' : 'Spaces'}
                    </p>
                    <Button asChild>
                        <Link href="/spaces/create" prefetch>
                            <Plus className="size-4" />
                            Create Space
                        </Link>
                    </Button>
                </div>

                {flash?.error && (
                    <Alert variant="destructive">
                        <AlertTitle>Heads up</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                {flash?.success && (
                    <Alert>
                        <AlertTitle>Done</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {spaces.length === 0 ? (
                    <div className="relative flex aspect-video items-center justify-center overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        <div className="relative flex flex-col items-center gap-3 text-center">
                            <p className="text-sm text-muted-foreground">
                                No spaces yet.
                            </p>
                            <Button asChild>
                                <Link href="/spaces/create" prefetch>
                                    <Plus className="size-4" />
                                    Create your first Space
                                </Link>
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {spaces.map((space) => (
                            <div
                                key={space.id}
                                className="relative flex flex-col justify-between rounded-xl border border-sidebar-border/70 p-4 transition hover:border-sidebar-border hover:shadow-sm"
                            >
                                <Link
                                    href={`/spaces/${space.slug}/dashboard`}
                                    className="flex flex-col gap-1"
                                    prefetch
                                >
                                    <span className="text-base font-medium">
                                        {space.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        /spaces/{space.slug}
                                    </span>
                                    <span className="mt-2 text-xs text-muted-foreground">
                                        {space.rating_enabled
                                            ? 'Rating enabled'
                                            : 'Rating disabled'}
                                    </span>
                                </Link>
                                <div className="mt-4 flex items-center justify-end">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setPendingDelete(space)}
                                    >
                                        <Trash2 className="size-4" />
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <Dialog
                open={pendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingDelete(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Delete "{pendingDelete?.name ?? ''}"?
                        </DialogTitle>
                        <DialogDescription>
                            This removes the Space and its dashboard, inbox, and
                            embed. Testimonials already collected stay archived
                            and the URL slug is reserved so nothing else can
                            claim it.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setPendingDelete(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmDelete}
                        >
                            Delete Space
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SpacePageShell>
    );
}
