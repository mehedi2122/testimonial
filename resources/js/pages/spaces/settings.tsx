import { SpacePageShell } from '@/components/space-page-shell';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import type { PageProps } from '@/types';

type SpaceSettingsProps = PageProps & {
    space: {
        id: number;
        slug: string;
        name: string;
    };
};

export default function SpaceSettings({ space }: SpaceSettingsProps) {
    return (
        <SpacePageShell
            title={`${space.name} — Settings`}
            description="Edit Space branding, fields, and consent text."
        >
            <div className="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                <div className="absolute inset-0 flex items-center justify-center">
                    <span className="text-sm text-muted-foreground">
                        Settings editor lands with a future OpenSpec change.
                    </span>
                </div>
            </div>
        </SpacePageShell>
    );
}
