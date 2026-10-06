import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Eye,
    EyeOff,
    Heart,
    Pencil,
    Star,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { SpacePageShell } from '@/components/space-page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PageProps } from '@/types';

type TestimonialValue = {
    field_key: string | null;
    label: string | null;
    value: string | null;
};

type Testimonial = {
    id: number;
    name: string;
    email: string;
    testimonial: string;
    rating: number | null;
    is_favorite: boolean;
    is_wall_of_love: boolean;
    is_hidden: boolean;
    submitted_at: string | null;
    values: TestimonialValue[];
};

type SpaceInboxProps = PageProps & {
    space: { id: number; slug: string; name: string };
    testimonials: Testimonial[];
    live_count: number;
    plan_limit: number;
    plan: string;
};

type FlashMessages = {
    success?: string;
    error?: string;
};

type SharedPageProps = {
    flash?: FlashMessages;
};

const inputClass =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

const updateRoute = (slug: string, id: number): string =>
    `/spaces/${slug}/inbox/${id}`;

const actionUrl = (
    slug: string,
    id: number,
    segment: 'favorite' | 'wall-of-love' | 'hidden',
): string => `/spaces/${slug}/inbox/${id}/${segment}`;

/**
 * Spaces inbox (OpenSpec change: testimonial-inbox). Owner-side
 * moderation queue: list live testimonials, toggle favorite / wall /
 * hidden, edit, soft-delete. Favorites float to the top via server
 * ordering; everything else is client-side state.
 */
export default function SpaceInbox({
    space,
    testimonials,
    live_count,
    plan_limit,
    plan,
}: SpaceInboxProps) {
    const { flash } = usePage<SharedPageProps>().props;
    const [pendingDelete, setPendingDelete] = useState<Testimonial | null>(
        null,
    );
    const [editing, setEditing] = useState<Testimonial | null>(null);

    const toggleFlag = (
        id: number,
        segment: 'favorite' | 'wall-of-love' | 'hidden',
    ): void => {
        router.post(
            actionUrl(space.slug, id, segment),
            {},
            { preserveScroll: true },
        );
    };

    const confirmDelete = (): void => {
        if (!pendingDelete) {
            return;
        }
        router.delete(updateRoute(space.slug, pendingDelete.id), {
            preserveScroll: true,
            onFinish: () => setPendingDelete(null),
        });
    };

    const closeEdit = (): void => setEditing(null);

    return (
        <SpacePageShell
            title={`${space.name} — Inbox`}
            description="Moderate new submissions. Mark favorites to pin at the top, hide to suppress the public wall, edit to fix typos, delete to remove entirely."
        >
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={`/spaces/${space.slug}/dashboard`} prefetch>
                            <ArrowLeft className="size-4" />
                            Back to dashboard
                        </Link>
                    </Button>
                    <p className="text-sm text-muted-foreground">
                        <span className="font-medium text-foreground">
                            {live_count}
                        </span>{' '}
                        of {plan_limit} collected
                        <span className="ml-2 text-xs tracking-wide text-muted-foreground uppercase">
                            ({plan} plan)
                        </span>
                    </p>
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

                {testimonials.length === 0 ? (
                    <div className="relative flex aspect-video items-center justify-center overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="relative flex flex-col items-center gap-3 text-center">
                            <p className="text-base font-medium">
                                No testimonials yet.
                            </p>
                            <p className="max-w-md text-sm text-muted-foreground">
                                Share your public link with customers —
                                <br />
                                <Link
                                    href={`/s/${space.slug}`}
                                    className="font-medium text-foreground underline-offset-4 hover:underline"
                                    target="_blank"
                                >
                                    /s/{space.slug}
                                </Link>{' '}
                                — and the submissions will appear here as they
                                come in.
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-col gap-4">
                        {testimonials.map((t) => (
                            <TestimonialRow
                                key={t.id}
                                testimonial={t}
                                onEdit={() => setEditing(t)}
                                onDelete={() => setPendingDelete(t)}
                                onToggleFavorite={() =>
                                    toggleFlag(t.id, 'favorite')
                                }
                                onToggleWallOfLove={() =>
                                    toggleFlag(t.id, 'wall-of-love')
                                }
                                onToggleHidden={() =>
                                    toggleFlag(t.id, 'hidden')
                                }
                            />
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
                        <DialogTitle>Delete this testimonial?</DialogTitle>
                        <DialogDescription>
                            This removes the testimonial from your inbox and the
                            wall of love. The submission email and field values
                            stay archived in case you want to restore them
                            later.
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
                            Delete testimonial
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <EditDialog
                testimonial={editing}
                onClose={closeEdit}
                spaceSlug={space.slug}
            />
        </SpacePageShell>
    );
}

type RowProps = {
    testimonial: Testimonial;
    onEdit: () => void;
    onDelete: () => void;
    onToggleFavorite: () => void;
    onToggleWallOfLove: () => void;
    onToggleHidden: () => void;
};

function TestimonialRow({
    testimonial,
    onEdit,
    onDelete,
    onToggleFavorite,
    onToggleWallOfLove,
    onToggleHidden,
}: RowProps) {
    const initials = testimonial.name
        .split(/\s+/)
        .map((part) => part.charAt(0).toUpperCase())
        .slice(0, 2)
        .join('');

    return (
        <article className="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 transition hover:border-sidebar-border hover:shadow-sm">
            <header className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Avatar>
                        <AvatarFallback className="text-xs font-medium">
                            {initials || '?'}
                        </AvatarFallback>
                    </Avatar>
                    <div className="flex flex-col">
                        <span className="text-sm font-medium">
                            {testimonial.name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {maskEmail(testimonial.email)}
                            <span className="mx-1.5">·</span>
                            <time
                                dateTime={testimonial.submitted_at ?? undefined}
                            >
                                {formatRelative(testimonial.submitted_at)}
                            </time>
                        </span>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-1">
                    {testimonial.is_hidden && (
                        <Badge variant="outline">Hidden</Badge>
                    )}
                    {testimonial.is_favorite && (
                        <Badge variant="default">Starred</Badge>
                    )}
                    {!testimonial.is_wall_of_love && (
                        <Badge variant="secondary">Off wall</Badge>
                    )}
                </div>
            </header>

            {testimonial.rating !== null && (
                <div
                    className="flex items-center gap-1"
                    aria-label={`${testimonial.rating} out of 5`}
                >
                    {[1, 2, 3, 4, 5].map((value) => (
                        <Star
                            key={value}
                            className={`size-4 ${
                                value <= testimonial.rating!
                                    ? 'fill-yellow-400 text-yellow-400'
                                    : 'text-muted-foreground/40'
                            }`}
                        />
                    ))}
                </div>
            )}

            <p className="text-sm leading-relaxed whitespace-pre-line">
                {testimonial.testimonial}
            </p>

            {testimonial.values.length > 0 && (
                <dl className="grid gap-1 border-t border-sidebar-border/40 pt-2 text-xs">
                    {testimonial.values.map((value) => (
                        <div
                            key={`${value.field_key ?? 'field'}-${
                                value.label ?? ''
                            }`}
                            className="flex flex-wrap gap-2"
                        >
                            <dt className="font-medium text-muted-foreground">
                                {value.label ?? value.field_key}:
                            </dt>
                            <dd>{value.value ?? ''}</dd>
                        </div>
                    ))}
                </dl>
            )}

            <footer className="flex flex-wrap items-center justify-end gap-1 border-t border-sidebar-border/40 pt-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onToggleFavorite}
                    aria-pressed={testimonial.is_favorite}
                    title={
                        testimonial.is_favorite
                            ? 'Remove from favorites'
                            : 'Mark as favorite'
                    }
                >
                    <Star
                        className={`size-4 ${
                            testimonial.is_favorite
                                ? 'fill-yellow-400 text-yellow-400'
                                : ''
                        }`}
                    />
                    {testimonial.is_favorite ? 'Starred' : 'Star'}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onToggleWallOfLove}
                    aria-pressed={testimonial.is_wall_of_love}
                    title={
                        testimonial.is_wall_of_love
                            ? 'Remove from wall of love'
                            : 'Publish to wall of love'
                    }
                >
                    <Heart
                        className={`size-4 ${
                            testimonial.is_wall_of_love
                                ? 'fill-red-500 text-red-500'
                                : ''
                        }`}
                    />
                    {testimonial.is_wall_of_love ? 'On wall' : 'Wall'}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onToggleHidden}
                    aria-pressed={testimonial.is_hidden}
                    title={testimonial.is_hidden ? 'Make visible' : 'Hide'}
                >
                    {testimonial.is_hidden ? (
                        <EyeOff className="size-4" />
                    ) : (
                        <Eye className="size-4" />
                    )}
                    {testimonial.is_hidden ? 'Hidden' : 'Hide'}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onEdit}
                >
                    <Pencil className="size-4" />
                    Edit
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onDelete}
                >
                    <Trash2 className="size-4" />
                    Delete
                </Button>
            </footer>
        </article>
    );
}

type EditDialogProps = {
    testimonial: Testimonial | null;
    onClose: () => void;
    spaceSlug: string;
};

function EditDialog({ testimonial, onClose, spaceSlug }: EditDialogProps) {
    const open = testimonial !== null;

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                if (!isOpen) {
                    onClose();
                }
            }}
        >
            <DialogContent>
                {testimonial && (
                    <EditForm
                        testimonial={testimonial}
                        onClose={onClose}
                        spaceSlug={spaceSlug}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

type EditFormProps = {
    testimonial: Testimonial;
    onClose: () => void;
    spaceSlug: string;
};

function EditForm({ testimonial, onClose, spaceSlug }: EditFormProps) {
    const [name, setName] = useState(testimonial.name);
    const [body, setBody] = useState(testimonial.testimonial);
    const [rating, setRating] = useState<number | null>(testimonial.rating);
    const [submitting, setSubmitting] = useState(false);

    const submit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        setSubmitting(true);

        router.patch(
            updateRoute(spaceSlug, testimonial.id),
            {
                name,
                testimonial: body,
                rating,
            },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
                onSuccess: () => onClose(),
            },
        );
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <DialogHeader>
                <DialogTitle>Edit testimonial</DialogTitle>
                <DialogDescription>
                    Fix a typo, polish the wording, or update the rating. The
                    submitter's email stays locked to the original submission.
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-2">
                <Label htmlFor={`edit-name-${testimonial.id}`}>Name</Label>
                <Input
                    id={`edit-name-${testimonial.id}`}
                    type="text"
                    required
                    minLength={2}
                    maxLength={120}
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`edit-body-${testimonial.id}`}>
                    Testimonial
                </Label>
                <textarea
                    id={`edit-body-${testimonial.id}`}
                    required
                    rows={5}
                    minLength={10}
                    maxLength={2000}
                    className={inputClass}
                    value={body}
                    onChange={(event) => setBody(event.target.value)}
                />
            </div>

            <div className="grid gap-2">
                <Label>Rating</Label>
                <div
                    className="flex items-center gap-1"
                    role="radiogroup"
                    aria-label="Rating"
                >
                    {[1, 2, 3, 4, 5].map((value) => {
                        const filled = rating !== null && value <= rating;
                        return (
                            <button
                                key={value}
                                type="button"
                                role="radio"
                                aria-checked={rating === value}
                                aria-label={`${value} star${
                                    value === 1 ? '' : 's'
                                }`}
                                className="rounded-sm p-1 transition hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                onClick={() =>
                                    setRating(rating === value ? null : value)
                                }
                            >
                                <Star
                                    className={`size-5 ${
                                        filled
                                            ? 'fill-yellow-400 text-yellow-400'
                                            : 'text-muted-foreground'
                                    }`}
                                />
                            </button>
                        );
                    })}
                    {rating !== null && (
                        <button
                            type="button"
                            className="ml-2 text-xs text-muted-foreground underline-offset-2 hover:underline"
                            onClick={() => setRating(null)}
                        >
                            Clear
                        </button>
                    )}
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={onClose}
                    disabled={submitting}
                >
                    Cancel
                </Button>
                <Button type="submit" disabled={submitting}>
                    Save changes
                </Button>
            </DialogFooter>
        </form>
    );
}

function maskEmail(email: string): string {
    const atIndex = email.indexOf('@');
    if (atIndex <= 1) {
        return email;
    }
    const local = email.slice(0, atIndex);
    const domain = email.slice(atIndex);
    const head = local.slice(0, 2);
    return `${head}${'•'.repeat(Math.max(local.length - 2, 1))}${domain}`;
}

function formatRelative(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    const then = new Date(iso).getTime();
    const now = Date.now();
    const diff = Math.max(0, now - then);
    const minute = 60_000;
    const hour = 60 * minute;
    const day = 24 * hour;

    if (diff < minute) {
        return 'just now';
    }
    if (diff < hour) {
        const n = Math.floor(diff / minute);
        return `${n}m ago`;
    }
    if (diff < day) {
        const n = Math.floor(diff / hour);
        return `${n}h ago`;
    }
    if (diff < 30 * day) {
        const n = Math.floor(diff / day);
        return `${n}d ago`;
    }
    return new Date(iso).toLocaleDateString();
}
