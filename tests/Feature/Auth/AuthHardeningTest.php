<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\SpaceTheme;
use App\Models\Space;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Sign-up / log-in problems found in the field (2026-10-09).
 */
class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_page_redirects_back_with_a_message_instead_of_419(): void
    {
        Route::middleware('web')->post('/_test/expired', fn () => abort(419));

        $this->from('/login')
            ->post('/_test/expired')
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'Your session expired. Please try again.');
    }

    public function test_expired_json_request_still_gets_419(): void
    {
        Route::middleware('web')->post('/_test/expired', fn () => abort(419));

        $this->postJson('/_test/expired')->assertStatus(419);
    }

    public function test_profile_email_is_stored_lowercase_so_login_keeps_working(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => '  New.Person@Example.COM '])
            ->assertSessionHasNoErrors();

        $this->assertSame('new.person@example.com', $user->fresh()?->email);

        auth()->logout();
        $this->post(route('login.store'), ['email' => 'New.Person@Example.com', 'password' => 'password']);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_profile_email_change_cannot_take_another_accounts_email_in_other_case(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'Taken@Example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_registration_with_capitalised_email_cannot_duplicate_an_account(): void
    {
        User::factory()->create(['email' => 'maya@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Maya Again',
            'email' => 'Maya@Example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->count());
    }

    public function test_local_environment_auto_verifies_on_login(): void
    {
        $this->bootLocalAutoVerify();
        $user = User::factory()->unverified()->create();

        event(new Login('web', $user, false));

        $this->assertTrue($user->fresh()?->hasVerifiedEmail());
    }

    public function test_local_environment_reverifies_after_email_change(): void
    {
        $this->bootLocalAutoVerify();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'changed@example.com']);

        $this->assertTrue($user->fresh()?->hasVerifiedEmail());
        $this->actingAs($user->fresh())->get(route('spaces.index'))->assertOk();
    }

    public function test_unknown_stored_theme_reads_as_minimal_instead_of_crashing(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        DB::table('spaces')->where('id', $space->id)->update(['theme' => 'minimal_light']);

        $this->assertSame(SpaceTheme::Minimal, $space->fresh()?->theme);
        $this->actingAs($user)->get(route('spaces.dashboard', ['space' => $space->slug]))->assertOk();
    }

    private function bootLocalAutoVerify(): void
    {
        $this->app['env'] = 'local';
        $method = new ReflectionMethod(AppServiceProvider::class, 'configureLocalAutoVerify');
        $method->invoke(new AppServiceProvider($this->app));
        $this->app['env'] = 'testing';
    }
}
