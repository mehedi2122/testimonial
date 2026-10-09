<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // routes/web.php re-registers Cashier's routes so the webhook
        // resolves to our StripeWebhookController subclass.
        Cashier::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiters();
        $this->configureLocalAutoVerify();
    }

    /**
     * In local environments mail goes to the log (MAIL_MAILER=log), so a
     * verification link never reaches anyone and the /spaces/* `verified`
     * gate would strand the account on "Verify your email". Auto-verify on
     * every way into that state: registration, login (accounts created
     * before this existed), and an email change in Settings. Production and
     * other non-local environments are unaffected and still require the
     * verification link.
     */
    protected function configureLocalAutoVerify(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $verify = function (mixed $user): void {
            if (! $user instanceof User || $user->hasVerifiedEmail()) {
                return;
            }

            // Write straight to the row: this can run inside the model's own
            // `saved` event, where a nested save() compares against the
            // not-yet-synced original and may skip the write entirely.
            $now = now();
            User::query()->whereKey($user->getKey())->update(['email_verified_at' => $now]);
            $user->forceFill(['email_verified_at' => $now])->syncOriginalAttribute('email_verified_at');
        };

        Event::listen(Registered::class, fn (Registered $event) => $verify($event->user));
        Event::listen(Login::class, fn (Login $event) => $verify($event->user));

        // Settings → Profile clears email_verified_at when the email changes.
        User::saved(function (User $user) use ($verify): void {
            if ($user->wasChanged('email')) {
                $verify($user);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Configure named rate limiters.
     *
     * `public-submissions`: 60 requests per IP per hour. Exceeding returns
     * 429 with the JSON body { error: 'rate_limited' } once group 11 wires
     * the error envelope. Defined here (not in bootstrap/app.php) because
     * the RateLimiter facade is not available during middleware binding.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('public-submissions', function (Request $request) {
            return Limit::perHour(60)->by($request->ip());
        });
    }
}
