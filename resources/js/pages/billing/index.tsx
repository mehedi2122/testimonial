import { router, usePage, usePoll } from '@inertiajs/react';
import { Check, CreditCard, LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { SpacePageShell } from '@/components/space-page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type PlanKey = 'free' | 'pro';

type PlanRow = {
    key: PlanKey;
    name: string;
    price: string;
    max_spaces: number;
    max_testimonials_per_space: number;
};

type SubscriptionState = {
    status: string;
    on_grace_period: boolean;
    ends_at: string | null;
    payment_failed: boolean;
};

type BillingProps = {
    plan: PlanKey;
    plans: PlanRow[];
    usage: { spaces: number; max_spaces: number };
    subscription: SubscriptionState | null;
    checkout: 'success' | 'canceled' | null;
    can_manage: boolean;
    configured: boolean;
};

type SharedPageProps = {
    flash?: { success?: string; error?: string };
};

/** Poll every 3s for up to a minute while the webhook is in flight. */
const POLL_INTERVAL_MS = 3000;
const MAX_POLLS = 20;

/**
 * Billing page (OpenSpec change: billing, PRD §26). Both plans side by
 * side with a current-plan indicator. Returning from Stripe with
 * `?checkout=success` does not make the user Pro — the page polls until
 * the webhook has written the subscription, then the `plan` prop flips.
 */
export default function BillingIndex({
    plan,
    plans,
    usage,
    subscription,
    checkout,
    can_manage,
    configured,
}: BillingProps) {
    const { flash } = usePage<SharedPageProps>().props;
    const [redirecting, setRedirecting] = useState<
        'checkout' | 'portal' | null
    >(null);
    const [polls, setPolls] = useState(0);

    const awaitingWebhook = checkout === 'success' && plan === 'free';
    const timedOut = awaitingWebhook && polls >= MAX_POLLS;

    const { stop } = usePoll(
        POLL_INTERVAL_MS,
        {
            only: ['plan', 'subscription', 'usage', 'can_manage'],
            onFinish: () => setPolls((n) => n + 1),
        },
        { autoStart: awaitingWebhook },
    );

    useEffect(() => {
        if (!awaitingWebhook || timedOut) {
            stop();
        }
    }, [awaitingWebhook, timedOut, stop]);

    const goTo = (target: 'checkout' | 'portal'): void => {
        setRedirecting(target);
        router.post(
            `/billing/${target}`,
            {},
            { onFinish: () => setRedirecting(null) },
        );
    };

    const endsAt =
        subscription?.ends_at != null
            ? new Date(subscription.ends_at).toLocaleDateString()
            : null;

    return (
        <SpacePageShell
            title="Billing"
            description="Your plan, its limits, and your subscription."
        >
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
                {flash?.error && (
                    <Alert variant="destructive">
                        <AlertTitle>Heads up</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                {awaitingWebhook && !timedOut && (
                    <Alert>
                        <LoaderCircle className="size-4 animate-spin" />
                        <AlertTitle>Payment received</AlertTitle>
                        <AlertDescription>
                            Activating Pro as soon as Stripe confirms your
                            subscription. This usually takes a few seconds.
                        </AlertDescription>
                    </Alert>
                )}

                {timedOut && (
                    <Alert>
                        <AlertTitle>Still waiting for Stripe</AlertTitle>
                        <AlertDescription>
                            Your payment went through, but we haven't heard back
                            from Stripe yet. Refresh this page in a minute.
                        </AlertDescription>
                    </Alert>
                )}

                {checkout === 'success' && plan === 'pro' && (
                    <Alert>
                        <Check className="size-4" />
                        <AlertTitle>You're on Pro</AlertTitle>
                        <AlertDescription>
                            Thanks for upgrading. Your new limits are active.
                        </AlertDescription>
                    </Alert>
                )}

                {checkout === 'canceled' && (
                    <Alert>
                        <AlertTitle>Checkout canceled</AlertTitle>
                        <AlertDescription>
                            No charge was made. You can upgrade any time.
                        </AlertDescription>
                    </Alert>
                )}

                {subscription?.payment_failed && (
                    <Alert variant="destructive">
                        <AlertTitle>Your last payment failed</AlertTitle>
                        <AlertDescription>
                            Update your payment method to get your Pro limits
                            back.
                        </AlertDescription>
                    </Alert>
                )}

                {subscription?.on_grace_period && endsAt && (
                    <Alert>
                        <AlertTitle>Pro ends on {endsAt}</AlertTitle>
                        <AlertDescription>
                            Your subscription is canceled. You keep Pro limits
                            until then; nothing is deleted afterwards.
                        </AlertDescription>
                    </Alert>
                )}

                <p className="text-sm text-muted-foreground">
                    Using {usage.spaces} of {usage.max_spaces} Spaces.
                </p>

                <div className="grid gap-4 md:grid-cols-2">
                    {plans.map((row) => {
                        const isCurrent = row.key === plan;

                        return (
                            <Card
                                key={row.key}
                                className={
                                    isCurrent ? 'border-primary' : undefined
                                }
                            >
                                <CardHeader>
                                    <div className="flex items-center justify-between gap-2">
                                        <CardTitle>{row.name}</CardTitle>
                                        {isCurrent && (
                                            <Badge>Current plan</Badge>
                                        )}
                                    </div>
                                    <CardDescription>
                                        <span className="text-2xl font-semibold text-foreground">
                                            {row.price}
                                        </span>
                                        /month
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ul className="flex flex-col gap-2 text-sm">
                                        <li className="flex items-center gap-2">
                                            <Check className="size-4 text-muted-foreground" />
                                            {row.max_spaces} Spaces
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="size-4 text-muted-foreground" />
                                            {row.max_testimonials_per_space.toLocaleString()}{' '}
                                            testimonials per Space
                                        </li>
                                    </ul>
                                </CardContent>
                                <CardFooter className="mt-auto">
                                    {row.key === 'pro' &&
                                        plan === 'free' &&
                                        !subscription?.payment_failed && (
                                            <Button
                                                className="w-full"
                                                disabled={
                                                    !configured ||
                                                    awaitingWebhook ||
                                                    redirecting !== null
                                                }
                                                onClick={() => goTo('checkout')}
                                            >
                                                {redirecting === 'checkout' && (
                                                    <LoaderCircle className="size-4 animate-spin" />
                                                )}
                                                Upgrade to Pro
                                            </Button>
                                        )}
                                    {row.key === 'pro' &&
                                        plan === 'pro' &&
                                        can_manage && (
                                            <Button
                                                variant="outline"
                                                className="w-full"
                                                disabled={redirecting !== null}
                                                onClick={() => goTo('portal')}
                                            >
                                                {redirecting === 'portal' ? (
                                                    <LoaderCircle className="size-4 animate-spin" />
                                                ) : (
                                                    <CreditCard className="size-4" />
                                                )}
                                                Manage subscription
                                            </Button>
                                        )}
                                </CardFooter>
                            </Card>
                        );
                    })}
                </div>

                {subscription?.payment_failed && can_manage && (
                    <div>
                        <Button
                            variant="outline"
                            disabled={redirecting !== null}
                            onClick={() => goTo('portal')}
                        >
                            <CreditCard className="size-4" />
                            Update payment method
                        </Button>
                    </div>
                )}

                {!configured && plan === 'free' && (
                    <p className="text-xs text-muted-foreground">
                        Upgrades are unavailable until STRIPE_PRICE_PRO is
                        configured.
                    </p>
                )}
            </div>
        </SpacePageShell>
    );
}
