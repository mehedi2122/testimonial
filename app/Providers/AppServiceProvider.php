<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
     * In local environments, auto-mark newly registered users as email-verified
     * so the /spaces/* verified-middleware gate does not block local sign-up →
     * log-in → dashboard testing. Production and other non-local environments
     * are unaffected and continue to require the user to click the
     * verification link.
     */
    protected function configureLocalAutoVerify(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        Event::listen(Registered::class, function (Registered $event): void {
            $user = $event->user;

            if (method_exists($user, 'hasVerifiedEmail') && $user->hasVerifiedEmail()) {
                return;
            }

            $user->forceFill(['email_verified_at' => now()])->save();
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
