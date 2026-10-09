import { Head } from '@inertiajs/react';
import { Pin, Star } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Card, CardContent } from '@/components/ui/card';

type SpaceData = {
    name: string;
    title: string;
    subtitle: string | null;
    theme: string;
};

type FieldData = {
    label: string;
    value: string;
    type: 'text' | 'url' | 'email' | 'image' | 'number';
};

type TestimonialData = {
    id: number;
    name: string;
    testimonial: string;
    rating: number | null;
    submitted_at: string | null;
    is_favorite: boolean;
    fields: FieldData[];
};

type WallPageProps = {
    space: SpaceData;
    testimonials: TestimonialData[];
    count: number;
};

/**
 * Public Wall of Love page (OpenSpec change: public-wall-of-love).
 *
 * The page respondents / prospects / press see at `/wall/{slug}`.
 * Mirrors `submit.tsx`'s `<section className="... space-theme-{theme}">`
 * chrome (no `AppShell`, no nav — public surface). Header with
 * `space.title` + `space.subtitle` + count line. Responsive masonry
 * grid (1/2/3 columns) of testimonial cards. Empty state card when
 * `count === 0`.
 *
 * No `<form>`, no admin actions, no email link. Email is the
 * respondent's PII and is excluded from `EmbedTestimonialResource` —
 * it is never in the payload to begin with, so a single React
 * component without the email field is sufficient.
 */
export default function PublicWall({
    space,
    testimonials,
    count,
}: WallPageProps) {
    return (
        <section
            className={`mx-auto max-w-6xl space-y-8 p-6 space-theme-${space.theme}`}
        >
            <Head
                title={
                    count > 0 ? `${space.name} — Wall of Love` : `${space.name}`
                }
            />

            <header className="space-y-2 text-center sm:text-left">
                <h1 className="text-4xl font-semibold tracking-tight">
                    {space.title}
                </h1>
                {space.subtitle && (
                    <p className="text-lg text-muted-foreground">
                        {space.subtitle}
                    </p>
                )}
                <p className="text-sm text-muted-foreground">
                    {count === 0
                        ? 'No testimonials yet'
                        : `${count} testimonial${count === 1 ? '' : 's'} from the community`}
                </p>
            </header>

            {count === 0 ? (
                <Card className="mx-auto max-w-md">
                    <CardContent className="space-y-2 p-6 text-center text-sm text-muted-foreground">
                        <p className="text-base font-medium text-foreground">
                            No testimonials yet
                        </p>
                        <p>
                            This Space hasn't collected any public testimonials.
                            Check back soon.
                        </p>
                    </CardContent>
                </Card>
            ) : (
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {testimonials.map((testimonial) => (
                        <TestimonialCard
                            key={testimonial.id}
                            testimonial={testimonial}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

function TestimonialCard({ testimonial }: { testimonial: TestimonialData }) {
    const initials = testimonial.name
        .split(/\s+/)
        .map((part) => part.charAt(0).toUpperCase())
        .slice(0, 2)
        .join('');

    return (
        <article className="relative flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-xs">
            {testimonial.is_favorite && (
                <span
                    aria-label="Pinned to top"
                    title="Pinned to top"
                    className="absolute top-3 right-3 inline-flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <Pin className="size-4" aria-hidden="true" />
                </span>
            )}

            <header className="flex items-center gap-3">
                <Avatar>
                    <AvatarFallback className="text-xs font-medium">
                        {initials || '?'}
                    </AvatarFallback>
                </Avatar>
                <div className="flex flex-col">
                    <span className="text-sm font-medium">
                        {testimonial.name}
                    </span>
                    {testimonial.submitted_at && (
                        <time
                            dateTime={testimonial.submitted_at}
                            className="text-xs text-muted-foreground"
                        >
                            {formatDate(testimonial.submitted_at)}
                        </time>
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
                            aria-hidden="true"
                        />
                    ))}
                </div>
            )}

            <p className="text-sm leading-relaxed whitespace-pre-line">
                {testimonial.testimonial}
            </p>

            {testimonial.fields.length > 0 && (
                <dl className="grid gap-1 border-t border-sidebar-border/40 pt-2 text-xs">
                    {testimonial.fields.map((field, index) => (
                        <div
                            key={`${field.label}-${index}`}
                            className="flex flex-wrap gap-2"
                        >
                            <dt className="font-medium text-muted-foreground">
                                {field.label}:
                            </dt>
                            {field.type === 'url' ? (
                                <dd>
                                    <a
                                        href={field.value}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="break-all text-primary underline-offset-2 hover:underline"
                                    >
                                        {field.value}
                                    </a>
                                </dd>
                            ) : (
                                <dd>{field.value}</dd>
                            )}
                        </div>
                    ))}
                </dl>
            )}
        </article>
    );
}

function formatDate(iso: string): string {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
