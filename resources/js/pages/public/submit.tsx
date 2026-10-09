import { Head } from '@inertiajs/react';
import { ImagePlus, LoaderCircle, Star, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
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

type Errors = Record<string, string>;

const MAX_PHOTO_BYTES = 2 * 1024 * 1024;

const inputClass =
    'border-input placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

/**
 * Public testimonial submission form (OpenSpec changes:
 * public-submission-form, public-submission-fixes). The page
 * respondents see at `/s/{public_id}`.
 *
 * The endpoint is a JSON API (201 / 422 / 429), not an Inertia route,
 * so the form posts with `fetch` + `FormData` — multipart, so a profile
 * photo can ride along — and maps the JSON errors back onto fields.
 * The whole page is wrapped in `space-theme-{theme}` (PRD §10).
 */
export default function PublicSubmit({ space, fields }: SubmitPageProps) {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [testimonial, setTestimonial] = useState('');
    const [rating, setRating] = useState<number | null>(null);
    const [consent, setConsent] = useState(false);
    const [values, setValues] = useState<Record<string, string>>({});
    const [photos, setPhotos] = useState<Record<string, File>>({});
    const [errors, setErrors] = useState<Errors>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [submitted, setSubmitted] = useState(false);

    const setPhoto = (key: string, file: File | null): void => {
        setErrors((prev) => {
            const next = { ...prev };
            delete next[`photos.${key}`];
            delete next[`fields.${key}`];
            return next;
        });
        setPhotos((prev) => {
            const next = { ...prev };
            if (file) {
                next[key] = file;
            } else {
                delete next[key];
            }
            return next;
        });

        if (file && file.size > MAX_PHOTO_BYTES) {
            setErrors((prev) => ({
                ...prev,
                [`photos.${key}`]: 'The photo must be 2 MB or smaller.',
            }));
        }
    };

    const submit = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
        event.preventDefault();
        setProcessing(true);
        setErrors({});
        setFormError(null);

        const body = new FormData();
        body.append('name', name);
        body.append('email', email);
        body.append('testimonial', testimonial);
        body.append('consent_given', consent ? '1' : '0');
        if (rating !== null) {
            body.append('rating', String(rating));
        }

        let index = 0;
        for (const field of fields) {
            if (field.type === 'image') {
                const file = photos[field.field_key];
                if (file) {
                    body.append(`photos[${field.field_key}]`, file);
                }
                continue;
            }

            const value = (values[field.field_key] ?? '').trim();
            if (value !== '') {
                body.append(`values[${index}][field_key]`, field.field_key);
                body.append(`values[${index}][value]`, value);
                index++;
            }
        }

        try {
            const response = await fetch(`/s/${space.public_id}/submissions`, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (response.status === 201) {
                setSubmitted(true);
                window.scrollTo({ top: 0 });
                return;
            }

            const json = (await response.json().catch(() => ({}))) as {
                message?: string;
                error?: string;
                plan?: string;
                limit?: number;
                errors?: Record<string, string[]>;
            };

            if (response.status === 422 && json.errors) {
                setErrors(
                    Object.fromEntries(
                        Object.entries(json.errors).map(([key, messages]) => [
                            key,
                            messages[0] ?? 'Invalid value.',
                        ]),
                    ),
                );
                setFormError('Please fix the highlighted fields.');
            } else if (json.error === 'limit_reached') {
                setFormError(
                    "This Space can't accept new testimonials right now. Please let the owner know.",
                );
            } else if (response.status === 429) {
                setFormError(
                    'Too many submissions from your network. Please try again later.',
                );
            } else if (response.status === 404) {
                setFormError('This testimonial page no longer exists.');
            } else {
                setFormError('Something went wrong. Please try again.');
            }
        } catch {
            setFormError(
                'We could not reach the server. Check your connection and try again.',
            );
        } finally {
            setProcessing(false);
        }
    };

    const fieldError = (key: string): string | undefined =>
        errors[`fields.${key}`] ?? errors[`photos.${key}`];

    return (
        <div
            className={`min-h-screen bg-background text-foreground space-theme-${space.theme}`}
        >
            <section className="mx-auto max-w-xl space-y-6 px-4 py-10 sm:px-6">
                {submitted ? (
                    <>
                        <Head title={`Thank you — ${space.name}`} />
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-2xl">
                                    🎉 Boom! Your testimonial has been
                                    submitted.
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <p className="text-base font-medium">
                                    You're officially awesome!
                                </p>
                                <p className="text-muted-foreground">
                                    The team behind{' '}
                                    <strong className="text-foreground">
                                        {space.name}
                                    </strong>{' '}
                                    will see it in their inbox. Thanks for
                                    taking the time.
                                </p>
                            </CardContent>
                        </Card>
                    </>
                ) : (
                    <>
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

                        {formError && (
                            <Alert variant="destructive">
                                <AlertTitle>Not submitted yet</AlertTitle>
                                <AlertDescription>{formError}</AlertDescription>
                            </Alert>
                        )}

                        <form
                            onSubmit={submit}
                            noValidate
                            className="flex flex-col gap-5 rounded-[var(--radius)] border bg-card p-5 text-card-foreground sm:p-6"
                        >
                            <TextField
                                id="name"
                                label="Name"
                                required
                                autoComplete="name"
                                value={name}
                                onChange={setName}
                                error={errors.name}
                            />
                            <TextField
                                id="email"
                                label="Email"
                                type="email"
                                required
                                autoComplete="email"
                                hint="Never shown publicly."
                                value={email}
                                onChange={setEmail}
                                error={errors.email}
                            />

                            {fields.map((field) =>
                                field.type === 'image' ? (
                                    <PhotoField
                                        key={field.field_key}
                                        field={field}
                                        file={photos[field.field_key] ?? null}
                                        onChange={(file) =>
                                            setPhoto(field.field_key, file)
                                        }
                                        error={fieldError(field.field_key)}
                                    />
                                ) : (
                                    <TextField
                                        key={field.field_key}
                                        id={field.field_key}
                                        label={field.label}
                                        type={inputTypeFor(field.type)}
                                        required={field.required}
                                        value={values[field.field_key] ?? ''}
                                        onChange={(value) =>
                                            setValues((prev) => ({
                                                ...prev,
                                                [field.field_key]: value,
                                            }))
                                        }
                                        error={fieldError(field.field_key)}
                                    />
                                ),
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="testimonial">
                                    Your testimonial
                                </Label>
                                <textarea
                                    id="testimonial"
                                    required
                                    rows={5}
                                    maxLength={2000}
                                    className={inputClass}
                                    placeholder="Tell us about your experience..."
                                    value={testimonial}
                                    aria-invalid={Boolean(errors.testimonial)}
                                    onChange={(event) =>
                                        setTestimonial(event.target.value)
                                    }
                                />
                                <FieldMessage error={errors.testimonial}>
                                    At least 10 characters.
                                </FieldMessage>
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
                                                rating !== null &&
                                                value <= rating;
                                            return (
                                                <button
                                                    key={value}
                                                    type="button"
                                                    role="radio"
                                                    aria-checked={
                                                        rating === value
                                                    }
                                                    aria-label={`${value} star${value === 1 ? '' : 's'}`}
                                                    className="rounded-sm p-1 transition hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    onClick={() =>
                                                        setRating(value)
                                                    }
                                                >
                                                    <Star
                                                        className={`size-7 ${
                                                            filled
                                                                ? 'fill-yellow-400 text-yellow-400'
                                                                : 'text-muted-foreground'
                                                        }`}
                                                    />
                                                </button>
                                            );
                                        })}
                                    </div>
                                    <FieldMessage error={errors.rating} />
                                </div>
                            )}

                            <div className="flex items-start gap-3 rounded-md border bg-background/50 p-3">
                                <Checkbox
                                    id="consent"
                                    checked={consent}
                                    onCheckedChange={(checked) =>
                                        setConsent(checked === true)
                                    }
                                />
                                <Label
                                    htmlFor="consent"
                                    className="text-sm leading-snug font-normal"
                                >
                                    I give permission for this testimonial to be
                                    shared publicly or on social media.
                                    <span className="ml-1 text-xs text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                size="lg"
                                disabled={processing}
                            >
                                {processing && (
                                    <LoaderCircle className="size-4 animate-spin" />
                                )}
                                Submit testimonial
                            </Button>
                        </form>
                    </>
                )}
            </section>
        </div>
    );
}

type TextFieldProps = {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    type?: string;
    required?: boolean;
    autoComplete?: string;
    hint?: string;
    error?: string;
};

function TextField({
    id,
    label,
    value,
    onChange,
    type = 'text',
    required = false,
    autoComplete,
    hint,
    error,
}: TextFieldProps) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                {label}
                {!required && (
                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                        (optional)
                    </span>
                )}
            </Label>
            <Input
                id={id}
                type={type}
                required={required}
                autoComplete={autoComplete}
                value={value}
                aria-invalid={Boolean(error)}
                onChange={(event) => onChange(event.target.value)}
            />
            <FieldMessage error={error}>{hint}</FieldMessage>
        </div>
    );
}

type PhotoFieldProps = {
    field: FieldData;
    file: File | null;
    onChange: (file: File | null) => void;
    error?: string;
};

function PhotoField({ field, file, onChange, error }: PhotoFieldProps) {
    const [preview, setPreview] = useState<string | null>(null);

    useEffect(() => {
        if (!file) {
            setPreview(null);
            return;
        }
        const url = URL.createObjectURL(file);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    const inputId = `photo-${field.field_key}`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={inputId}>
                {field.label}
                {!field.required && (
                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                        (optional)
                    </span>
                )}
            </Label>
            <div className="flex items-center gap-3">
                <div className="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full border bg-muted">
                    {preview ? (
                        <img
                            src={preview}
                            alt="Selected photo preview"
                            className="size-full object-cover"
                        />
                    ) : (
                        <ImagePlus className="size-5 text-muted-foreground" />
                    )}
                </div>
                <label
                    htmlFor={inputId}
                    className="cursor-pointer rounded-md border bg-background px-3 py-2 text-sm font-medium shadow-xs transition focus-within:ring-2 focus-within:ring-ring hover:bg-accent"
                >
                    {file ? 'Change photo' : 'Choose photo'}
                    <input
                        id={inputId}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        onChange={(event) =>
                            onChange(event.target.files?.[0] ?? null)
                        }
                    />
                </label>
                {file && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Remove photo"
                        onClick={() => onChange(null)}
                    >
                        <X className="size-4" />
                    </Button>
                )}
            </div>
            <FieldMessage error={error}>
                JPG, PNG or WebP, up to 2 MB.
            </FieldMessage>
        </div>
    );
}

function FieldMessage({
    error,
    children,
}: {
    error?: string;
    children?: React.ReactNode;
}) {
    if (error) {
        return (
            <p className="text-xs text-destructive" role="alert">
                {error}
            </p>
        );
    }

    return children ? (
        <p className="text-xs text-muted-foreground">{children}</p>
    ) : null;
}

function inputTypeFor(fieldType: FieldData['type']): string {
    switch (fieldType) {
        case 'url':
            return 'url';
        case 'email':
            return 'email';
        case 'number':
            return 'number';
        default:
            return 'text';
    }
}
