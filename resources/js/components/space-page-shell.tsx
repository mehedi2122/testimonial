import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Props = {
    title: string;
    description?: string;
    children: ReactNode;
};

/**
 * Shared chrome for /spaces/* placeholder pages. Sets the document title
 * (prepended with the Space name when one is active) and a standard
 * heading + description block. Real dashboards replace this with their
 * own layout; it stays only for the Agentic Application Shell baseline.
 */
export function SpacePageShell({ title, description, children }: Props) {
    const fullTitle = `${title} | Testimonials`;

    return (
        <>
            <Head title={fullTitle} />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    {description && (
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                <div className="flex flex-1 flex-col">{children}</div>
            </div>
        </>
    );
}
