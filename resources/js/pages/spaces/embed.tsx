import { SpacePageShell } from '@/components/space-page-shell';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import type { PageProps } from '@/types';

type SpaceEmbedProps = PageProps & {
    space: {
        id: number;
        slug: string;
        name: string;
        public_id: string;
    };
};

export default function SpaceEmbed({ space }: SpaceEmbedProps) {
    return (
        <SpacePageShell
            title={`${space.name} — Embed`}
            description="Drop this snippet into any site to start collecting testimonials."
        >
            <div className="flex flex-1 flex-col gap-4">
                <div className="rounded-xl border border-sidebar-border/70 p-4 font-mono text-xs dark:border-sidebar-border">
                    <code>{`<script src="${window.location.origin}/embed.js" data-space="${space.public_id}"></script>`}</code>
                </div>
                <div className="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    <div className="absolute inset-0 flex items-center justify-center">
                        <span className="text-sm text-muted-foreground">
                            Embed customization lands with a future OpenSpec
                            change.
                        </span>
                    </div>
                </div>
            </div>
        </SpacePageShell>
    );
}
