import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Star } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type SpaceData = {
    public_id: string;
    name: string;
    title: string;
    subtitle: string | null;
    ask: string;
    theme: string;
    rating_enabled: boolean;
};

type FieldData = {
    field_key: string;
    label: string;
    type: 'text' | 'url' | 'email' | 'image' | 'number';
    mode: 'off' | 'optional' | 'required';
    required: boolean;
};

type SubmitPageProps = {
    space: SpaceData;
    fields: FieldData[];
};

type SubmissionForm = {
    name: string;
    email: string;
    testimonial: string;
    rating: number | null;
    consent_given: boolean;
    values: Record<string, string>;
};

const inputClass =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

/**
 * Public testimonial submission form (OpenSpec change:
 * public-submission-form). The page respondents see at the Space's
 * `/s/{public_id}` URL — renders title/subtitle/ask, configured
 * fields (skipping mode = Off), rating (when enabled), consent, and
 * swaps to a thank-you card in place on a 201.
 */
export default function PublicSubmit({ space, fields }: SubmitPageProps) {
    const [submitted, setSubmitted] = useState(false);
    const [limitReached, setLimitReached] = useState<{
        plan: string;
        limit: number;
    } | null>(null);

    const { data, setData, post, processing, errors } = useForm<SubmissionForm>(
        {
            name: '',
            email: '',
            testimonial: '',
            rating: space.rating_enabled ? null : null,
            consent_given: false,
            values: Object.fromEntries(fields.map((f) => [f.field_key, ''])),
        },
    );

    const submit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        setLimitReached(null);

        post(`/s/${space.public_id}/submissions`, {
            preserveScroll: true,
            data: {
                name: data.name,
                email: data.email,
                testimonial: data.testimonial,
                rating: data.rating,
                consent_given: true,
                values: fields.map((f) => ({
                    field_key: f.field_key,
                    value: data.values[f.field_key] ?? '',
                })),
            },
            onSuccess: () => setSubmitted(true),
            onError: (errorsBag) => {
                const limitError = errorsBag as unknown as {
                    error?: string;
                    plan?: string;
                    limit?: number;
                };
                if (
                    limitError?.error === 'limit_reached' &&
                    limitError.plan !== undefined &&
                    limitError.limit !== undefined
                ) {
                    setLimitReached({
                        plan: limitError.plan,
                        limit: limitError.limit,
                    });
                }
            },
        });
    };

    const setFieldValue = (key: string, value: string): void => {
        setData('values', { ...data.values, [key]: value });
    };

    if (submitted) {
        return (
            <section
                className={`mx-auto max-w-xl space-y-6 p-6 space-theme-${space.theme}`}
            >
                <Head title={`Thank you — ${space.name}`} />
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">🎉 Boom!</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <p>
                            Your testimonial has been submitted. You're
                            officially awesome.
                        </p>
                        <p className="text-muted-foreground">
                            The team behind <strong>{space.name}</strong> will
                            see it in their inbox. Thanks for taking the time.
                        </p>
                    </CardContent>
                </Card>
            </section>
        );
    }

    return (
        <section
            className={`mx-auto max-w-xl space-y-6 p-6 space-theme-${space.theme}`}
        >
            <Head title={`Submit to ${space.name}`} />
            <header className="space-y-2">
                <h1 className="text-3xl font-semibold tracking-tight">
                    {space.title}
                </h1>
                {space.subtitle && (
                    <p className="text-sm text-muted-foreground">
                        {space.subtitle}
                    </p>
                )}
                <p className="text-base">{space.ask}</p>
            </header>

            {limitReached && (
                <Alert variant="destructive">
                    <AlertTitle>
                        This Space can't accept new testimonials right now
                    </AlertTitle>
                    <AlertDescription>
                        This Space has reached its {limitReached.plan} plan
                        limit of {limitReached.limit} testimonials. Please ask
                        the owner to upgrade.
                    </AlertDescription>
                </Alert>
            )}

            <form
                onSubmit={submit}
                className="flex flex-col gap-5 rounded-lg border border-sidebar-border/70 bg-card p-6"
            >
                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autoFocus
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                    />
                    {errors.name && (
                        <p className="text-xs text-destructive">
                            {errors.name}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                    />
                    {errors.email && (
                        <p className="text-xs text-destructive">
                            {errors.email}
                        </p>
                    )}
                </div>

                {fields.map((field) => (
                    <div key={field.field_key} className="grid gap-2">
                        <Label htmlFor={field.field_key}>
                            {field.label}
                            {!field.required && (
                                <span className="ml-2 text-xs text-muted-foreground">
                                    (optional)
                                </span>
                            )}
                        </Label>
                        <Input
                            id={field.field_key}
                            type={inputTypeFor(field.type)}
                            required={field.required}
                            value={data.values[field.field_key] ?? ''}
                            onChange={(event) =>
                                setFieldValue(
                                    field.field_key,
                                    event.target.value,
                                )
                            }
                        />
                        {errors[
                            `values.${field.field_key}` as keyof typeof errors
                        ] && (
                            <p className="text-xs text-destructive">
                                {String(
                                    errors[
                                        `values.${field.field_key}` as keyof typeof errors
                                    ],
                                )}
                            </p>
                        )}
                    </div>
                ))}

                <div className="grid gap-2">
                    <Label htmlFor="testimonial">Your testimonial</Label>
                    <textarea
                        id="testimonial"
                        required
                        rows={5}
                        minLength={10}
                        maxLength={2000}
                        className={inputClass}
                        placeholder="Tell us about your experience..."
                        value={data.testimonial}
                        onChange={(event) =>
                            setData('testimonial', event.target.value)
                        }
                    />
                    {errors.testimonial && (
                        <p className="text-xs text-destructive">
                            {errors.testimonial}
                        </p>
                    )}
                </div>

                {space.rating_enabled && (
                    <div className="grid gap-2">
                        <Label>Rating</Label>
                        <div
                            className="flex items-center gap-1"
                            role="radiogroup"
                            aria-label="Rating"
                        >
                            {[1, 2, 3, 4, 5].map((value) => {
                                const filled =
                                    data.rating !== null &&
                                    value <= data.rating;
                                return (
                                    <button
                                        key={value}
                                        type="button"
                                        role="radio"
                                        aria-checked={data.rating === value}
                                        aria-label={`${value} star${value === 1 ? '' : 's'}`}
                                        className="rounded-sm p-1 transition hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        onClick={() =>
                                            setData(
                                                'rating',
                                                data.rating === value
                                                    ? null
                                                    : value,
                                            )
                                        }
                                    >
                                        <Star
                                            className={`size-6 ${
                                                filled
                                                    ? 'fill-yellow-400 text-yellow-400'
                                                    : 'text-muted-foreground'
                                            }`}
                                        />
                                    </button>
                                );
                            })}
                            {data.rating !== null && (
                                <button
                                    type="button"
                                    className="ml-2 text-xs text-muted-foreground underline-offset-2 hover:underline"
                                    onClick={() => setData('rating', null)}
                                >
                                    Clear
                                </button>
                            )}
                        </div>
                        {errors.rating && (
                            <p className="text-xs text-destructive">
                                {errors.rating}
                            </p>
                        )}
                    </div>
                )}

                <div className="flex items-start gap-3 rounded-md border border-sidebar-border/70 bg-background p-3">
                    <Checkbox
                        id="consent"
                        checked={data.consent_given}
                        onCheckedChange={(checked) =>
                            setData('consent_given', checked === true)
                        }
                        aria-required="true"
                    />
                    <Label htmlFor="consent" className="text-sm leading-snug">
                        I give permission for this testimonial to be shared
                        publicly or on social media.
                    </Label>
                </div>
                {errors.consent_given && (
                    <p className="text-xs text-destructive">
                        {errors.consent_given}
                    </p>
                )}

                <Button
                    type="submit"
                    disabled={processing || !data.consent_given}
                >
                    {processing && (
                        <LoaderCircle className="size-4 animate-spin" />
                    )}
                    Submit testimonial
                </Button>
            </form>
        </section>
    );
}

function inputTypeFor(fieldType: FieldData['type']): string {
    switch (fieldType) {
        case 'url':
            return 'url';
        case 'email':
            return 'email';
        case 'number':
            return 'number';
        case 'image':
        case 'text':
        default:
            return 'text';
    }
}
