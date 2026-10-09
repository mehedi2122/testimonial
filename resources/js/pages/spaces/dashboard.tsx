import { Head, Link, usePage } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { SpacePageShell } from '@/components/space-page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

type Analytics = {
    range: '7d' | '30d' | '90d' | 'all';
    total: number;
    favorites: number;
    on_wall: number;
    hidden: number;
    average_rating: number | null;
    rating_enabled: boolean;
    rating_histogram: [number, number, number, number, number];
    submissions_per_day: Array<{ date: string; count: number }>;
    plan: 'free' | 'pro';
    plan_limit: number;
    live_count: number;
};

type SpaceDashboardProps = PageProps & {
    space: {
        id: number;
        slug: string;
        name: string;
    };
    analytics: Analytics;
};

type FlashMessages = {
    success?: string;
    error?: string;
};

type SharedPageProps = {
    flash?: FlashMessages;
};

const RANGES: Array<{ value: Analytics['range']; label: string }> = [
    { value: '7d', label: 'Last 7 days' },
    { value: '30d', label: 'Last 30 days' },
    { value: '90d', label: 'Last 90 days' },
    { value: 'all', label: 'All time' },
];

/**
 * Spaces dashboard (OpenSpec change: dashboard-analytics). Owner-facing
 * analytics page (PRD §6 + §30). Stat cards, 5-bar rating histogram,
 * daily submissions sparkline, date-range filter, and a Free-plan
 * upgrade banner. Charts are pure SVG — no charting library.
 *
 * URL is the source of truth for the active range: `?range=7d` etc.
 * Chips are `<Link>` tags so back/forward and shareable URLs work.
 */
export default function SpaceDashboard({
    space,
    analytics,
}: SpaceDashboardProps) {
    const { flash } = usePage<SharedPageProps>().props;
    const showUpgradeBanner = analytics.plan === 'free';

    return (
        <SpacePageShell
            title={`${space.name} — Dashboard`}
            description="See how your testimonials are performing over time."
        >
            <div className="flex flex-col gap-6">
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

                {showUpgradeBanner && (
                    <Alert>
                        <AlertTitle>You're on the Free plan</AlertTitle>
                        <AlertDescription>
                            {analytics.live_count} of {analytics.plan_limit}{' '}
                            testimonials collected. Upgrade to Pro to collect up
                            to 1,000 per Space.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 md:grid-cols-3">
                    <KpiCard
                        label="Testimonials"
                        value={String(analytics.total)}
                        hint={`${analytics.favorites} favorited · ${analytics.hidden} hidden`}
                    />
                    <KpiCard
                        label="Average rating"
                        value={
                            analytics.rating_enabled
                                ? analytics.average_rating === null
                                    ? '—'
                                    : analytics.average_rating.toFixed(1)
                                : '—'
                        }
                        hint={
                            analytics.rating_enabled
                                ? 'Across all rated submissions'
                                : 'Ratings disabled for this Space'
                        }
                    />
                    <KpiCard
                        label="On wall of love"
                        value={String(analytics.on_wall)}
                        hint="Currently visible on the public wall"
                    />
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div className="flex flex-col gap-1.5">
                            <CardTitle>Submissions per day</CardTitle>
                            <CardDescription>
                                {analytics.range === 'all'
                                    ? 'Since the first testimonial was collected.'
                                    : `Last ${
                                          analytics.range === '7d'
                                              ? '7'
                                              : analytics.range === '30d'
                                                ? '30'
                                                : '90'
                                      } days.`}
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap items-center gap-1 rounded-md border border-sidebar-border/70 p-1">
                            {RANGES.map((r) => {
                                const active = r.value === analytics.range;
                                return (
                                    <Link
                                        key={r.value}
                                        href={`/spaces/${space.slug}/dashboard?range=${r.value}`}
                                        preserveScroll
                                        className={cn(
                                            'rounded-sm px-3 py-1 text-xs font-medium transition-colors',
                                            active
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                        )}
                                    >
                                        {r.label}
                                    </Link>
                                );
                            })}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Sparkline data={analytics.submissions_per_day} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Rating distribution</CardTitle>
                        <CardDescription>
                            {analytics.rating_enabled
                                ? 'Counts of 1★ through 5★ across live testimonials.'
                                : 'Ratings are disabled for this Space.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {analytics.rating_enabled ? (
                            <Histogram data={analytics.rating_histogram} />
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Enable ratings in{' '}
                                <Link
                                    href={`/spaces/${space.slug}/settings`}
                                    className="underline"
                                >
                                    Settings
                                </Link>{' '}
                                to collect 1–5 star reviews.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </SpacePageShell>
    );
}

function KpiCard({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl tabular-nums">{value}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-xs text-muted-foreground">{hint}</p>
            </CardContent>
        </Card>
    );
}

function Histogram({
    data,
}: {
    data: [number, number, number, number, number];
}) {
    const max = Math.max(1, ...data);
    return (
        <div className="grid grid-cols-5 items-end gap-3">
            {data.map((count, i) => {
                const rating = i + 1;
                const heightPct = (count / max) * 100;
                return (
                    <div
                        key={rating}
                        className="flex flex-col items-center gap-2"
                    >
                        <div className="text-xs text-muted-foreground tabular-nums">
                            {count}
                        </div>
                        <div className="flex h-32 w-full items-end">
                            <div
                                className="w-full rounded-t-md bg-primary"
                                style={{
                                    height: `${heightPct}%`,
                                    minHeight: 2,
                                }}
                                aria-label={`${count} ratings at ${rating} stars`}
                            />
                        </div>
                        <div className="flex items-center gap-0.5 text-xs text-muted-foreground">
                            {rating}
                            <Star className="size-3 fill-current" />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function Sparkline({ data }: { data: Array<{ date: string; count: number }> }) {
    if (data.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No submissions in this range.
            </p>
        );
    }

    const max = Math.max(1, ...data.map((d) => d.count));
    const gap = 2;
    const barWidth = Math.max(
        4,
        Math.floor((100 - gap * (data.length - 1)) / data.length),
    );

    return (
        <div className="flex h-32 items-end gap-[2px]">
            {data.map((d) => {
                const heightPct = (d.count / max) * 100;
                return (
                    <div
                        key={d.date}
                        className="flex flex-1 flex-col items-center gap-1"
                    >
                        <div
                            className="w-full rounded-t-sm bg-primary/70 transition-colors hover:bg-primary"
                            style={{
                                height: `${heightPct}%`,
                                minHeight: 2,
                                maxWidth: `${barWidth}px`,
                            }}
                            title={`${d.date}: ${d.count} submission${
                                d.count === 1 ? '' : 's'
                            }`}
                            aria-label={`${d.count} submissions on ${d.date}`}
                        />
                    </div>
                );
            })}
        </div>
    );
}
