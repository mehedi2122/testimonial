import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';
import { SpacePageShell } from '@/components/space-page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PageProps } from '@/types';

type ThemeOption = {
    value: string;
    label: string;
};

type SpaceSettings = {
    id: number;
    slug: string;
    name: string;
    title: string;
    subtitle: string | null;
    ask: string;
    theme: string;
    rating_enabled: boolean;
    public_id: string;
    created_at: string | null;
};

type SpaceSettingsProps = PageProps & {
    space: SpaceSettings;
    themes: ThemeOption[];
};

type UpdateSpaceForm = {
    name: string;
    title: string;
    subtitle: string;
    ask: string;
    theme: string;
    rating_enabled: boolean;
};

type FlashMessages = {
    success?: string;
    error?: string;
};

type SharedPageProps = {
    flash?: FlashMessages;
};

/**
 * Space-settings editor (OpenSpec change: space-settings). Mirrors
 * `create.tsx` — same form chrome, same `useForm` shape, same Alert
 * pattern for errors. The form is pre-filled from the Inertia `space`
 * prop so an owner can revise any field without re-typing. Slug is
 * derived server-side on a name change; the success flash lands the
 * user on the new URL.
 */
export default function SpaceSettings({ space, themes }: SpaceSettingsProps) {
    const { flash } = usePage<SharedPageProps>().props;
    const { data, setData, patch, processing, errors } =
        useForm<UpdateSpaceForm>({
            name: space.name,
            title: space.title,
            subtitle: space.subtitle ?? '',
            ask: space.ask,
            theme: space.theme,
            rating_enabled: space.rating_enabled,
        });

    const submit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        patch(`/spaces/${space.slug}/settings`);
    };

    return (
        <SpacePageShell
            title={`${space.name} — Settings`}
            description="Edit Space branding, prompt copy, and the public rating toggle."
        >
            <form
                onSubmit={submit}
                className="mx-auto flex w-full max-w-xl flex-col gap-6"
            >
                {flash?.success && (
                    <Alert>
                        <AlertTitle>Done</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {Object.keys(errors).length > 0 && (
                    <Alert variant="destructive">
                        <AlertTitle>Couldn't save the settings</AlertTitle>
                        <AlertDescription>
                            Check the highlighted fields and try again.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="name">Space name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autoFocus
                        placeholder="Customer feedback"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                    />
                    <p className="text-xs text-muted-foreground">
                        Internal label. Used in the sidebar and switcher only.
                    </p>
                    {errors.name && (
                        <p className="text-xs text-destructive">
                            {errors.name}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="title">Public title</Label>
                    <Input
                        id="title"
                        type="text"
                        required
                        placeholder="What our customers say"
                        value={data.title}
                        onChange={(event) =>
                            setData('title', event.target.value)
                        }
                    />
                    {errors.title && (
                        <p className="text-xs text-destructive">
                            {errors.title}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="subtitle">Subtitle</Label>
                    <Input
                        id="subtitle"
                        type="text"
                        placeholder="Optional one-liner shown under the title"
                        value={data.subtitle}
                        onChange={(event) =>
                            setData('subtitle', event.target.value)
                        }
                    />
                    {errors.subtitle && (
                        <p className="text-xs text-destructive">
                            {errors.subtitle}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="ask">Ask</Label>
                    <Input
                        id="ask"
                        type="text"
                        required
                        placeholder="What do you think about our product?"
                        value={data.ask}
                        onChange={(event) => setData('ask', event.target.value)}
                    />
                    <p className="text-xs text-muted-foreground">
                        The prompt respondents see above the form.
                    </p>
                    {errors.ask && (
                        <p className="text-xs text-destructive">{errors.ask}</p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="theme">Theme</Label>
                    <Select
                        value={data.theme}
                        onValueChange={(value) => setData('theme', value)}
                    >
                        <SelectTrigger id="theme" className="w-full">
                            <SelectValue placeholder="Pick a theme" />
                        </SelectTrigger>
                        <SelectContent>
                            {themes.map((theme) => (
                                <SelectItem
                                    key={theme.value}
                                    value={theme.value}
                                >
                                    {theme.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {errors.theme && (
                        <p className="text-xs text-destructive">
                            {errors.theme}
                        </p>
                    )}
                </div>

                <div className="flex items-center gap-3">
                    <Checkbox
                        id="rating_enabled"
                        checked={data.rating_enabled}
                        onCheckedChange={(checked) =>
                            setData('rating_enabled', checked === true)
                        }
                    />
                    <Label htmlFor="rating_enabled">
                        Let respondents include a 1–5 star rating
                    </Label>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="public_id">Public ID (embed snippet)</Label>
                    <Input
                        id="public_id"
                        type="text"
                        readOnly
                        value={space.public_id}
                    />
                    <p className="text-xs text-muted-foreground">
                        Immutable. This is the key your embed script uses to
                        load the right Space.
                    </p>
                </div>

                <div className="flex items-center justify-between border-t border-sidebar-border/70 pt-6">
                    <Button
                        type="button"
                        variant="ghost"
                        asChild
                        disabled={processing}
                    >
                        <Link href={`/spaces/${space.slug}/dashboard`}>
                            <ArrowLeft className="size-4" />
                            Back
                        </Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && (
                            <LoaderCircle className="size-4 animate-spin" />
                        )}
                        Save changes
                    </Button>
                </div>
            </form>
        </SpacePageShell>
    );
}
