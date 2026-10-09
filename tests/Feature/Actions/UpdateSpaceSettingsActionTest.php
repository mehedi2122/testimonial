<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\UpdateSpaceSettingsAction;
use App\Enums\SpaceTheme;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Action-level coverage for the space-settings OpenSpec change. Mirrors
 * the InboxActionTest / CreateSpaceActionTest class style — action in
 * isolation, no HTTP.
 */
class UpdateSpaceSettingsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_persists_all_mutable_fields(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create([
            'name' => 'Original',
            'title' => 'Original title',
            'subtitle' => 'Original subtitle',
            'ask' => 'Original ask copy',
            'theme' => SpaceTheme::Minimal,
            'rating_enabled' => true,
        ]);

        $action = new UpdateSpaceSettingsAction;
        $updated = $action->update($space, [
            'name' => 'Renamed',
            'title' => 'New title',
            'subtitle' => 'New subtitle',
            'ask' => 'New ask copy',
            'theme' => SpaceTheme::Clean->value,
            'rating_enabled' => false,
        ]);

        $fresh = $updated->fresh();
        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame('New title', $fresh->title);
        $this->assertSame('New subtitle', $fresh->subtitle);
        $this->assertSame('New ask copy', $fresh->ask);
        $this->assertSame(SpaceTheme::Clean, $fresh->theme);
        $this->assertFalse($fresh->rating_enabled);
    }

    public function test_rename_regenerates_slug(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create(['name' => 'Shiplog Reviews']);

        $action = new UpdateSpaceSettingsAction;
        $updated = $action->update($space, [
            'name' => 'Shiplog Reviews v2',
            'title' => $space->title,
            'subtitle' => $space->subtitle,
            'ask' => $space->ask,
            'theme' => $space->theme->value,
            'rating_enabled' => $space->rating_enabled,
        ]);

        $this->assertSame('shiplog-reviews-v2', $updated->slug);
        $this->assertSame('shiplog-reviews-v2', $updated->fresh()->slug);
    }

    public function test_rename_to_occupied_slug_appends_suffix(): void
    {
        $user = User::factory()->create();
        // Existing row already owns the slug "roadmap".
        $existing = Space::factory()->for($user)->create([
            'name' => 'Roadmap',
            'slug' => 'roadmap',
        ]);
        $other = Space::factory()->for($user)->create(['name' => 'Temp Holder']);

        $action = new UpdateSpaceSettingsAction;
        $updated = $action->update($other, [
            'name' => 'Roadmap',
            'title' => $other->title,
            'subtitle' => $other->subtitle,
            'ask' => $other->ask,
            'theme' => $other->theme->value,
            'rating_enabled' => $other->rating_enabled,
        ]);

        $this->assertNotSame('roadmap', $updated->slug);
        $this->assertStringStartsWith('roadmap-', $updated->slug);
        $this->assertSame('roadmap', $existing->fresh()->slug);
    }

    public function test_same_name_keeps_current_slug(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create(['name' => 'Echo']);

        $action = new UpdateSpaceSettingsAction;
        $updated = $action->update($space, [
            'name' => 'Echo',
            'title' => 'New title only',
            'subtitle' => null,
            'ask' => 'New ask only',
            'theme' => SpaceTheme::Modern->value,
            'rating_enabled' => false,
        ]);

        $this->assertSame($space->slug, $updated->slug);
    }

    public function test_public_id_is_immutable_across_updates(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $originalPublicId = $space->public_id;

        $action = new UpdateSpaceSettingsAction;
        $action->update($space, [
            'name' => 'Renamed once',
            'title' => $space->title,
            'subtitle' => $space->subtitle,
            'ask' => $space->ask,
            'theme' => $space->theme->value,
            'rating_enabled' => $space->rating_enabled,
        ]);

        $this->assertSame($originalPublicId, $space->fresh()->public_id);

        $action->update($space->fresh(), [
            'name' => 'Renamed twice',
            'title' => $space->title,
            'subtitle' => $space->subtitle,
            'ask' => $space->ask,
            'theme' => $space->theme->value,
            'rating_enabled' => $space->rating_enabled,
        ]);

        $this->assertSame($originalPublicId, $space->fresh()->public_id);
    }

    public function test_rating_enabled_off_does_not_null_existing_ratings(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create(['rating_enabled' => true]);
        $a = Testimonial::factory()->for($space)->create(['rating' => 5]);
        $b = Testimonial::factory()->for($space)->create(['rating' => 4]);

        $action = new UpdateSpaceSettingsAction;
        $action->update($space, [
            'name' => $space->name,
            'title' => $space->title,
            'subtitle' => $space->subtitle,
            'ask' => $space->ask,
            'theme' => $space->theme->value,
            'rating_enabled' => false,
        ]);

        $this->assertFalse($space->fresh()->rating_enabled);
        $this->assertSame(5, $a->fresh()->rating);
        $this->assertSame(4, $b->fresh()->rating);
    }
}
