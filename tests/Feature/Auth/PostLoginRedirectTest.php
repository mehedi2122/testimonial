<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the Agentic Application Shell redirect rules (session 13-09):
 *  - 0 spaces → /spaces
 *  - 1+ spaces → /spaces/{slug}/dashboard (first space)
 */
class PostLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_to_spaces_index_when_user_has_no_spaces(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('spaces.index', absolute: false));
    }

    public function test_login_redirects_to_first_space_dashboard_when_user_has_spaces(): void
    {
        $user = User::factory()->create();
        $first = Space::factory()->for($user)->create();
        Space::factory()->for($user)->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(
            route('spaces.dashboard', ['space' => $first->slug], absolute: false),
        );
    }

    public function test_registration_redirects_to_spaces_index(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('spaces.index', absolute: false));
    }
}
