import { Link } from '@inertiajs/react';
import { ArrowRight, Code2, ExternalLink, Inbox } from 'lucide-react';
import { CopyButton } from '@/components/copy-button';
import { SpacePageShell } from '@/components/space-page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type CreatedProps = {
    space: { slug: string; name: string; title: string };
    public_url: string;
    wall_url: string;
};

/**
 * Space-creation success page (PRD §11). The one thing the owner needs
 * next is the public link, so it leads with that: Copy Link and View
 * Space, then the natural next stops (dashboard, inbox, embed).
 */
export default function SpaceCreated({
    space,
    public_url,
    wall_url,
}: CreatedProps) {
    return (
        <SpacePageShell
            title={`🎉 "${space.name}" is live`}
            description="Share this link with customers. Anyone can submit a testimonial — no account needed."
        >
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <div className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <Label htmlFor="public_url">Public link</Label>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input
                            id="public_url"
                            value={public_url}
                            readOnly
                            className="font-mono text-sm"
                            onFocus={(e) => e.currentTarget.select()}
                        />
                        <div className="flex gap-2">
                            <CopyButton
                                value={public_url}
                                label="Copy link"
                                size="default"
                                className="flex-1 sm:flex-none"
                            />
                            <Button asChild className="flex-1 sm:flex-none">
                                <a
                                    href={public_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLink className="size-4" />
                                    View Space
                                </a>
                            </Button>
                        </div>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Testimonials you add to your Wall of Love appear at{' '}
                        <a
                            href={wall_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="font-medium text-foreground underline-offset-4 hover:underline"
                        >
                            {wall_url}
                        </a>
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="default">
                        <Link href={`/spaces/${space.slug}/dashboard`} prefetch>
                            Go to dashboard
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={`/spaces/${space.slug}/inbox`} prefetch>
                            <Inbox className="size-4" />
                            Inbox
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={`/spaces/${space.slug}/embed`} prefetch>
                            <Code2 className="size-4" />
                            Create an embed
                        </Link>
                    </Button>
                </div>
            </div>
        </SpacePageShell>
    );
}
