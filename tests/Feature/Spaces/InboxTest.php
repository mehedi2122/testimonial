<?php

declare(strict_types=1);

namespace Tests\Feature\Spaces;

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP coverage for the testimonial-inbox OpenSpec change.
 * Mirrors the SpaceCrudTest / PublicSubmissionFormTest class style.
 */
class InboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_page_renders_for_owner(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->count(2)->create();

        $response = $this->actingAs($user)->get(route('spaces.inbox', ['space' => $space->slug]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('spaces/inbox')
            ->where('space.slug', $space->slug)
            ->where('live_count', 2)
            ->where('plan_limit', 100) // Free plan default
            ->where('plan', 'free')
            ->has('testimonials', 2)
        );
    }

    public function test_inbox_orders_favorites_first_then_newest(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $old = Testimonial::factory()->for($space)->create([
            'submitted_at' => now()->subDays(5),
            'is_favorite' => false,
        ]);
        $newest = Testimonial::factory()->for($space)->create([
            'submitted_at' => now()->subDay(),
            'is_favorite' => false,
        ]);
        $favorited = Testimonial::factory()->for($space)->create([
            'submitted_at' => now()->subDays(3),
            'is_favorite' => true,
        ]);

        $response = $this->actingAs($user)->get(route('spaces.inbox', ['space' => $space->slug]));

        $response->assertInertia(fn ($page) => $page
            ->where('testimonials.0.id', $favorited->id)
            ->where('testimonials.1.id', $newest->id)
            ->where('testimonials.2.id', $old->id)
        );
    }

    public function test_inbox_excludes_soft_deleted_testimonials(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->create();
        $gone = Testimonial::factory()->for($space)->create();
        $gone->delete();

        $response = $this->actingAs($user)->get(route('spaces.inbox', ['space' => $space->slug]));

        $response->assertInertia(fn ($page) => $page
            ->where('live_count', 1)
        );
    }

    public function test_inbox_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('spaces.inbox', ['space' => $space->slug]))
            ->assertForbidden();
    }

    public function test_favorite_toggles_flag(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($space)->create(['is_favorite' => false]);

        $response = $this->actingAs($user)->post(
            route('spaces.inbox.favorite', ['space' => $space->slug, 'testimonial' => $testimonial->id]),
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue($testimonial->fresh()->is_favorite);
    }

    public function test_wall_of_love_toggles_flag_and_recomputes_is_public(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($space)->create([
            'consent_given' => true,
            'is_wall_of_love' => true,
            'is_hidden' => false,
        ]);

        $this->actingAs($user)->post(
            route('spaces.inbox.wall-of-love', ['space' => $space->slug, 'testimonial' => $testimonial->id]),
        )->assertRedirect();

        $fresh = $testimonial->fresh();
        $this->assertFalse($fresh->is_wall_of_love);
        $this->assertFalse($fresh->is_public);
    }

    public function test_hidden_toggles_flag_and_recomputes_is_public(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($space)->create([
            'consent_given' => true,
            'is_wall_of_love' => true,
            'is_hidden' => false,
        ]);

        $this->actingAs($user)->post(
            route('spaces.inbox.hidden', ['space' => $space->slug, 'testimonial' => $testimonial->id]),
        )->assertRedirect();

        $fresh = $testimonial->fresh();
        $this->assertTrue($fresh->is_hidden);
        $this->assertFalse($fresh->is_public);
    }

    public function test_update_persists_validated_fields(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($space)->create([
            'name' => 'Old name',
            'testimonial' => 'Old body that is long enough.',
            'rating' => 3,
        ]);

        $response = $this->actingAs($user)->patch(
            route('spaces.inbox.update', ['space' => $space->slug, 'testimonial' => $testimonial->id]),
            [
                'name' => 'New name',
                'testimonial' => 'New body copy here.',
                'rating' => 5,
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $fresh = $testimonial->fresh();
        $this->assertSame('New name', $fresh->name);
        $this->assertSame('New body copy here.', $fresh->testimonial);
        $this->assertSame(5, $fresh->rating);
        // Email is locked at submission — must NOT change.
        $this->assertSame($testimonial->email, $fresh->email);
    }

    public function test_update_rejects_invalid_payload(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($space)->create();

        $response = $this->actingAs($user)->patch(
            route('spaces.inbox.update', ['space' => $space->slug, 'testimonial' => $testimonial->id]),
            [
                'name' => 'a', // too short
                'testimonial' => 'short', // too short
                'rating' => 9, // out of range
            ],
        );

        $response->assertSessionHasErrors(['name', 'testimonial', 'rating']);
    }

    public function test_destroy_soft_deletes(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($space)->create();

        $response = $this->actingAs($user)->delete(
            route('spaces.inbox.destroy', ['space' => $space->slug, 'testimonial' => $testimonial->id]),
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertNull(Testimonial::query()->find($testimonial->id));
    }

    public function test_routes_return_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($owner)->create();
        $testimonial = Testimonial::factory()->for($space)->create();

        $this->actingAs($other)->post(
            route('spaces.inbox.favorite', ['space' => $space->slug, 'testimonial' => $testimonial->id]),
        )->assertForbidden();

        $this->actingAs($other)->delete(
            route('spaces.inbox.destroy', ['space' => $space->slug, 'testimonial' => $testimonial->id]),
        )->assertForbidden();
    }

    public function test_wrong_space_scoped_testimonial_returns_404(): void
    {
        $user = User::factory()->create();
        $mine = Space::factory()->for($user)->create();
        $theirs = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($theirs)->create();

        // Hit the /mine/inbox/{theirs-testimonial}/favorite endpoint.
        $this->actingAs($user)->post(
            route('spaces.inbox.favorite', ['space' => $mine->slug, 'testimonial' => $testimonial->id]),
        )->assertNotFound();
    }

    public function test_destroy_soft_delete_removes_from_inbox_view(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $a = Testimonial::factory()->for($space)->create();
        $b = Testimonial::factory()->for($space)->create();

        $this->actingAs($user)->delete(
            route('spaces.inbox.destroy', ['space' => $space->slug, 'testimonial' => $a->id]),
        );

        $this->actingAs($user)
            ->get(route('spaces.inbox', ['space' => $space->slug]))
            ->assertInertia(fn ($page) => $page
                ->where('live_count', 1)
            );
    }

    public function test_wall_of_love_is_refused_without_sharing_consent(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($space)->create([
            'consent_given' => false,
            'is_wall_of_love' => false,
        ]);

        $this->actingAs($user)
            ->from(route('spaces.inbox', ['space' => $space->slug]))
            ->post(route('spaces.inbox.wall-of-love', ['space' => $space->slug, 'testimonial' => $testimonial->id]))
            ->assertSessionHas('error');

        $this->assertFalse($testimonial->fresh()?->is_wall_of_love);
    }

    public function test_wall_of_love_can_always_be_turned_off(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($space)->create([
            'consent_given' => false,
            'is_wall_of_love' => true,
        ]);

        $this->actingAs($user)
            ->post(route('spaces.inbox.wall-of-love', ['space' => $space->slug, 'testimonial' => $testimonial->id]))
            ->assertSessionHas('success');

        $this->assertFalse($testimonial->fresh()?->is_wall_of_love);
    }

    public function test_inbox_exposes_consent_and_never_a_storage_path(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->create(['consent_given' => false]);

        $this->actingAs($user)
            ->get(route('spaces.inbox', ['space' => $space->slug]))
            ->assertInertia(fn ($page) => $page
                ->where('testimonials.0.consent_given', false)
                ->where('testimonials.0.photo_url', null)
            );
    }
}
