import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, Copy, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { SpacePageShell } from '@/components/space-page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PageProps } from '@/types';

type LayoutOption = {
    value: 'masonry' | 'carousel';
    label: string;
};

type FieldRow = {
    key: string;
    label: string;
    show_in_embed: boolean;
};

type PreviewTestimonialValue = {
    field_key: string | null;
    label: string | null;
    value: string | null;
};

type PreviewTestimonial = {
    id: number;
    name: string;
    testimonial: string;
    rating: number | null;
    is_favorite: boolean;
    submitted_at: string | null;
    values: PreviewTestimonialValue[];
};

type SpaceEmbedProps = PageProps & {
    space: {
        id: number;
        slug: string;
        name: string;
        public_id: string;
        rating_enabled: boolean;
    };
    embed: {
        layout: 'masonry' | 'carousel';
        dark_mode: boolean;
        animation_enabled: boolean;
        show_rating: boolean;
        background_color: string | null;
        item_limit: number;
    };
    fields: FieldRow[];
    layouts: LayoutOption[];
    testimonials: PreviewTestimonial[];
    snippet: string;
};

type EmbedForm = {
    layout: 'masonry' | 'carousel';
    dark_mode: boolean;
    animation_enabled: boolean;
    show_rating: boolean;
    background_color: string;
    item_limit: number;
    field_visibility: Record<string, boolean>;
};

type FlashMessages = {
    success?: string;
    error?: string;
};

type SharedPageProps = {
    flash?: FlashMessages;
};

const MAX_ITEM_LIMIT = 50;

const inputClass =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

const hexColorRegex = /^#[0-9A-Fa-f]{6}$/;

/**
 * Embed Builder (OpenSpec change: embed-builder, PRD §19–§22).
 *
 * Three panes on `lg+`, stacked on mobile:
 *   - Configuration form (left): every EmbedConfiguration field plus
 *     one row-toggle per SpaceField.
 *   - Live preview (right): up to 6 publicly-visible testimonials in
 *     either a masonry-style column or a horizontal carousel, with
 *     the chosen background + dark-mode colors applied. Pure CSS —
 *     no new JS dep.
 *   - Embed code (bottom): the rendered snippet string from the
 *     server, plus a Copy-to-clipboard button.
 *
 * Form state is local React state (mirrored into the Inertia `data`
 * for the PATCH submit). The preview is a pure function of form
 * state + the `testimonials` Inertia prop, so re-renders are free.
 */
export default function SpaceEmbed({
    space,
    embed,
    fields,
    layouts,
    testimonials,
    snippet,
}: SpaceEmbedProps) {
    const { flash } = usePage<SharedPageProps>().props;

    const initialVisibility: Record<string, boolean> = {};
    for (const f of fields) {
        initialVisibility[f.key] = f.show_in_embed;
    }

    const { data, setData, patch, processing, errors } = useForm<EmbedForm>({
        layout: embed.layout,
        dark_mode: embed.dark_mode,
        animation_enabled: embed.animation_enabled,
        show_rating: embed.show_rating,
        background_color: embed.background_color ?? '',
        item_limit: embed.item_limit,
        field_visibility: initialVisibility,
    });

    const submit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        patch(`/spaces/${space.slug}/embed`);
    };

    const setVisibility = (key: string, value: boolean): void => {
        setData('field_visibility', {
            ...data.field_visibility,
            [key]: value,
        });
    };

    const previewList = testimonials.slice(0, data.item_limit);
    const showRatingForPreview = space.rating_enabled && data.show_rating;
    const backgroundStyle =
        data.background_color !== '' &&
        hexColorRegex.test(data.background_color)
            ? { backgroundColor: data.background_color }
            : undefined;

    return (
        <SpacePageShell
            title={`${space.name} — Embed`}
            description="Configure the layout, theme, and visibility of the testimonials widget you paste on your site."
        >
            <Head title={`${space.name} — Embed`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={`/spaces/${space.slug}/dashboard`} prefetch>
                            <ArrowLeft className="size-4" />
                            Back to dashboard
                        </Link>
                    </Button>
                </div>

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

                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-2">
                    <div className="flex flex-col gap-6">
                        <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-sm font-medium">Layout</h2>
                            <p className="mb-3 text-xs text-muted-foreground">
                                How testimonials are arranged on your site.
                            </p>
                            <div
                                className="grid grid-cols-2 gap-2"
                                role="radiogroup"
                                aria-label="Layout"
                            >
                                {layouts.map((option) => {
                                    const selected =
                                        data.layout === option.value;
                                    return (
                                        <button
                                            key={option.value}
                                            type="button"
                                            role="radio"
                                            aria-checked={selected}
                                            onClick={() =>
                                                setData('layout', option.value)
                                            }
                                            className={`rounded-md border px-3 py-2 text-sm transition ${
                                                selected
                                                    ? 'border-foreground bg-foreground/5 font-medium'
                                                    : 'border-sidebar-border/70 hover:border-sidebar-border dark:border-sidebar-border'
                                            }`}
                                        >
                                            {option.label}
                                        </button>
                                    );
                                })}
                            </div>
                            {errors.layout && (
                                <p className="mt-2 text-xs text-destructive">
                                    {errors.layout}
                                </p>
                            )}
                        </div>

                        <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-sm font-medium">Theme</h2>
                            <p className="mb-3 text-xs text-muted-foreground">
                                Choose light or dark, and an optional background
                                color.
                            </p>
                            <div className="flex flex-col gap-3">
                                <SwitchRow
                                    label="Dark mode"
                                    description="Use a dark background for the widget."
                                    checked={data.dark_mode}
                                    onChange={(v) => setData('dark_mode', v)}
                                />
                                <SwitchRow
                                    label="Animation"
                                    description="Fade cards in as they enter the viewport."
                                    checked={data.animation_enabled}
                                    onChange={(v) =>
                                        setData('animation_enabled', v)
                                    }
                                />
                                <div className="grid gap-2">
                                    <Label htmlFor="background_color">
                                        Background color
                                    </Label>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            id="background_color"
                                            type="color"
                                            value={
                                                data.background_color === ''
                                                    ? '#ffffff'
                                                    : data.background_color
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'background_color',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-9 w-16 cursor-pointer p-1"
                                        />
                                        <Input
                                            type="text"
                                            value={data.background_color}
                                            onChange={(event) =>
                                                setData(
                                                    'background_color',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="#0F172A or leave empty"
                                            className={`${inputClass} font-mono text-xs`}
                                        />
                                        {data.background_color !== '' && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setData(
                                                        'background_color',
                                                        '',
                                                    )
                                                }
                                            >
                                                Clear
                                            </Button>
                                        )}
                                    </div>
                                    {errors.background_color && (
                                        <p className="text-xs text-destructive">
                                            {errors.background_color}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-sm font-medium">Visibility</h2>
                            <p className="mb-3 text-xs text-muted-foreground">
                                What to show on each testimonial card.
                            </p>
                            <div className="flex flex-col gap-3">
                                <SwitchRow
                                    label="Show star rating"
                                    description={
                                        space.rating_enabled
                                            ? 'Display the 1–5 star rating row on each card.'
                                            : 'Ratings are disabled for this Space — turn on ratings in Settings first.'
                                    }
                                    checked={
                                        space.rating_enabled && data.show_rating
                                    }
                                    onChange={(v) => setData('show_rating', v)}
                                    disabled={!space.rating_enabled}
                                />
                                {fields.map((f) => (
                                    <SwitchRow
                                        key={f.key}
                                        label={`Show ${f.label.toLowerCase()}`}
                                        description={`Field "${f.key}" appears on each card when on.`}
                                        checked={
                                            data.field_visibility[f.key] ??
                                            false
                                        }
                                        onChange={(v) =>
                                            setVisibility(f.key, v)
                                        }
                                    />
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-sm font-medium">Limit</h2>
                            <p className="mb-3 text-xs text-muted-foreground">
                                How many testimonials to fetch per page load.
                            </p>
                            <div className="grid gap-2">
                                <Label htmlFor="item_limit">
                                    Testimonials per embed
                                </Label>
                                <Input
                                    id="item_limit"
                                    type="number"
                                    min={1}
                                    max={MAX_ITEM_LIMIT}
                                    value={data.item_limit}
                                    onChange={(event) =>
                                        setData(
                                            'item_limit',
                                            Number(event.target.value) || 1,
                                        )
                                    }
                                    className="w-24"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Capped at {MAX_ITEM_LIMIT} so a single embed
                                    never hangs your page.
                                </p>
                                {errors.item_limit && (
                                    <p className="text-xs text-destructive">
                                        {errors.item_limit}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-2 border-t border-sidebar-border/70 pt-4">
                            <Button type="submit" disabled={processing}>
                                {processing && (
                                    <LoaderCircle className="size-4 animate-spin" />
                                )}
                                Save embed settings
                            </Button>
                        </div>
                    </div>

                    <div className="flex flex-col gap-6 lg:sticky lg:top-4 lg:self-start">
                        <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-sm font-medium">
                                    Live preview
                                </h2>
                                <span className="text-xs text-muted-foreground">
                                    {previewList.length} of{' '}
                                    {testimonials.length} testimonial
                                    {testimonials.length === 1 ? '' : 's'}
                                </span>
                            </div>
                            <div
                                className={`overflow-hidden rounded-lg border p-3 ${
                                    data.dark_mode
                                        ? 'border-zinc-700 bg-zinc-900 text-zinc-100'
                                        : 'border-sidebar-border/70 bg-white text-zinc-900'
                                }`}
                                style={backgroundStyle}
                            >
                                {previewList.length === 0 ? (
                                    <p
                                        className={`py-8 text-center text-xs ${
                                            data.dark_mode
                                                ? 'text-zinc-400'
                                                : 'text-muted-foreground'
                                        }`}
                                    >
                                        No public testimonials yet — once
                                        submissions come in, the preview will
                                        show them here.
                                    </p>
                                ) : data.layout === 'carousel' ? (
                                    <div className="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2">
                                        {previewList.map((t) => (
                                            <PreviewCard
                                                key={t.id}
                                                testimonial={t}
                                                fields={fields}
                                                fieldVisibility={
                                                    data.field_visibility
                                                }
                                                showRating={
                                                    showRatingForPreview
                                                }
                                                dark={data.dark_mode}
                                            />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        {previewList.map((t) => (
                                            <PreviewCard
                                                key={t.id}
                                                testimonial={t}
                                                fields={fields}
                                                fieldVisibility={
                                                    data.field_visibility
                                                }
                                                showRating={
                                                    showRatingForPreview
                                                }
                                                dark={data.dark_mode}
                                            />
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        <EmbedCodeCard snippet={snippet} />
                    </div>
                </form>
            </div>
        </SpacePageShell>
    );
}

type SwitchRowProps = {
    label: string;
    description: string;
    checked: boolean;
    onChange: (value: boolean) => void;
    disabled?: boolean;
};

function SwitchRow({
    label,
    description,
    checked,
    onChange,
    disabled = false,
}: SwitchRowProps) {
    return (
        <div className="flex items-start justify-between gap-3">
            <div className="flex flex-col">
                <span
                    className={`text-sm font-medium ${disabled ? 'text-muted-foreground' : ''}`}
                >
                    {label}
                </span>
                <span className="text-xs text-muted-foreground">
                    {description}
                </span>
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                aria-label={label}
                disabled={disabled}
                onClick={() => onChange(!checked)}
                className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 ${
                    checked
                        ? 'border-foreground/40 bg-foreground'
                        : 'border-sidebar-border/70 bg-muted dark:border-sidebar-border'
                }`}
            >
                <span
                    className={`inline-block size-4 rounded-full bg-white shadow transition ${
                        checked ? 'translate-x-6' : 'translate-x-1'
                    }`}
                />
            </button>
        </div>
    );
}

type PreviewCardProps = {
    testimonial: PreviewTestimonial;
    fields: FieldRow[];
    fieldVisibility: Record<string, boolean>;
    showRating: boolean;
    dark: boolean;
};

function PreviewCard({
    testimonial,
    fields,
    fieldVisibility,
    showRating,
    dark,
}: PreviewCardProps) {
    const initials = testimonial.name
        .split(/\s+/)
        .map((part) => part.charAt(0).toUpperCase())
        .slice(0, 2)
        .join('');

    return (
        <article
            className={`flex w-full min-w-65 shrink-0 flex-col gap-2 rounded-md border p-3 text-xs ${
                dark
                    ? 'border-zinc-700 bg-zinc-800'
                    : 'border-sidebar-border/70 bg-white'
            }`}
        >
            <div className="flex items-center gap-2">
                <span
                    className={`flex size-7 items-center justify-center rounded-full text-[10px] font-medium ${
                        dark
                            ? 'bg-zinc-700 text-zinc-200'
                            : 'bg-muted text-muted-foreground'
                    }`}
                >
                    {initials || '?'}
                </span>
                <span className="font-medium">{testimonial.name}</span>
            </div>
            {showRating && testimonial.rating !== null && (
                <div className="flex items-center gap-0.5">
                    {[1, 2, 3, 4, 5].map((value) => (
                        <span
                            key={value}
                            className={
                                value <= testimonial.rating!
                                    ? 'text-yellow-400'
                                    : dark
                                      ? 'text-zinc-600'
                                      : 'text-zinc-300'
                            }
                        >
                            ★
                        </span>
                    ))}
                </div>
            )}
            <p className="line-clamp-3 leading-relaxed">
                {testimonial.testimonial}
            </p>
            {fields.length > 0 && (
                <dl className="flex flex-col gap-0.5 border-t border-current/10 pt-2">
                    {fields
                        .filter((f) => fieldVisibility[f.key])
                        .map((f) => {
                            const v = testimonial.values.find(
                                (val) => val.field_key === f.key,
                            );
                            if (!v || !v.value) {
                                return null;
                            }
                            return (
                                <div
                                    key={f.key}
                                    className="flex flex-wrap gap-1"
                                >
                                    <dt
                                        className={`font-medium ${
                                            dark
                                                ? 'text-zinc-400'
                                                : 'text-muted-foreground'
                                        }`}
                                    >
                                        {f.label}:
                                    </dt>
                                    <dd className="truncate">{v.value}</dd>
                                </div>
                            );
                        })}
                </dl>
            )}
        </article>
    );
}

type EmbedCodeCardProps = {
    snippet: string;
};

function EmbedCodeCard({ snippet }: EmbedCodeCardProps) {
    const [copied, setCopied] = useState(false);

    const copy = async (): Promise<void> => {
        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(snippet);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = snippet;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            // Silently no-op — the snippet is still visible in the <pre>
            // below, so the owner can still copy manually.
        }
    };

    return (
        <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-sm font-medium">Embed code</h2>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={copy}
                >
                    {copied ? (
                        <>
                            <Check className="size-4" />
                            Copied
                        </>
                    ) : (
                        <>
                            <Copy className="size-4" />
                            Copy
                        </>
                    )}
                </Button>
            </div>
            <pre className="overflow-x-auto rounded-md bg-muted p-3 font-mono text-xs leading-relaxed">
                <code>{snippet}</code>
            </pre>
            <p className="mt-2 text-xs text-muted-foreground">
                Paste this on your site. The embed widget that reads these
                attributes ships in a future release.
            </p>
        </div>
    );
}
