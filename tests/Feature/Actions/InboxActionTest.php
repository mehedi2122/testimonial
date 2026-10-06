<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Inbox\DeleteTestimonialAction;
use App\Actions\Inbox\ToggleFavoriteAction;
use App\Actions\Inbox\ToggleHiddenAction;
use App\Actions\Inbox\ToggleWallOfLoveAction;
use App\Actions\Inbox\UpdateTestimonialAction;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Action-level coverage for the inbox OpenSpec change.
 * Mirrors CreateSpaceActionTest style — class-based, no controller.
 */
class InboxActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_favorite_flips_state_and_persists(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($space)->create(['is_favorite' => false]);

        $action = new ToggleFavoriteAction;

        $first = $action->toggle($testimonial);
        $this->assertTrue($first);
        $this->assertTrue($testimonial->fresh()->is_favorite);

        $second = $action->toggle($testimonial->fresh());
        $this->assertFalse($second);
        $this->assertFalse($testimonial->fresh()->is_favorite);
    }

    public function test_toggle_wall_of_love_flips_state_and_refreshes_is_public(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        // On wall + consented + visible = is_public true
        $testimonial = Testimonial::factory()->for($space)->create([
            'consent_given' => true,
            'is_wall_of_love' => true,
            'is_hidden' => false,
        ]);
        $this->assertTrue($testimonial->fresh()->is_public);

        $action = new ToggleWallOfLoveAction;
        $isOnWall = $action->toggle($testimonial);

        $this->assertFalse($isOnWall);
        // SQLite recompute via the saving hook — is_public should be false
        $this->assertFalse($testimonial->fresh()->is_public);
    }

    public function test_toggle_hidden_flips_state_and_refreshes_is_public(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($space)->create([
            'consent_given' => true,
            'is_wall_of_love' => true,
            'is_hidden' => false,
        ]);
        $this->assertTrue($testimonial->fresh()->is_public);

        $action = new ToggleHiddenAction;
        $isHidden = $action->toggle($testimonial);

        $this->assertTrue($isHidden);
        $this->assertFalse($testimonial->fresh()->is_public);

        // Toggle back — is_public flips back to true.
        $action->toggle($testimonial->fresh());
        $this->assertTrue($testimonial->fresh()->is_public);
    }

    public function test_update_sanitizes_name_and_testimonial(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($space)->create([
            'name' => 'Original Name',
            'testimonial' => 'Original body copy.',
            'rating' => 4,
        ]);

        $action = new UpdateTestimonialAction;
        $updated = $action->update($testimonial, [
            'name' => '<script>alert("x")</script>Alice',
            'testimonial' => 'Hello <b>world</b> & "quoted" copy.',
            'rating' => 5,
        ]);

        $this->assertSame(5, $updated->rating);
        // Sanitization is htmlspecialchars() — script tags become literal
        // text. We test that "<script" no longer appears.
        $fresh = $updated->fresh();
        $this->assertStringNotContainsString('<script', $fresh->name);
        $this->assertStringContainsString('&lt;', $fresh->name);
        $this->assertStringNotContainsString('<b>', $fresh->testimonial);
    }

    public function test_update_accepts_null_rating(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($space)->create(['rating' => 3]);

        $action = new UpdateTestimonialAction;
        $action->update($testimonial, [
            'name' => 'Still rated',
            'testimonial' => 'Body still here.',
            'rating' => null,
        ]);

        $this->assertNull($testimonial->fresh()->rating);
    }

    public function test_delete_soft_deletes_the_testimonial(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $testimonial = Testimonial::factory()->for($space)->create();

        (new DeleteTestimonialAction)->delete($testimonial);

        // Default queries (with SoftDeletes global scope) hide the row.
        $this->assertNull(Testimonial::query()->find($testimonial->id));
        // withTrashed() reveals it with a deleted_at timestamp.
        $trashed = Testimonial::query()->withTrashed()->find($testimonial->id);
        $this->assertNotNull($trashed);
        $this->assertNotNull($trashed->deleted_at);
    }
}
