<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\CreateSpaceAction;
use App\Enums\Plan;
use App\Enums\SpaceFieldMode;
use App\Enums\SpaceTheme;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-ish coverage for the Space-creation action (OpenSpec change:
 * space-crud). Tests the helper directly — no HTTP — so the action's
 * invariants can move independently of the controller.
 *
 * Class-based Pest test (matches PostLoginRedirectTest style).
 */
class CreateSpaceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_plan_limit_returns_null_below_caps(): void
    {
        $user = User::factory()->create();
        Space::factory()->for($user)->count(2)->create();

        $action = new CreateSpaceAction;

        $this->assertNull($action->checkPlanLimit($user->fresh()));
    }

    public function test_check_plan_limit_returns_violation_for_free_user_at_3_spaces(): void
    {
        $user = User::factory()->create();
        Space::factory()->for($user)->count(3)->create();

        $action = new CreateSpaceAction;
        $result = $action->checkPlanLimit($user->fresh());

        $this->assertNotNull($result);
        $this->assertSame('limit_reached', $result['error']);
        $this->assertSame(Plan::Free, $result['plan']);
        $this->assertSame(3, $result['limit']);
    }

    public function test_check_plan_limit_ignores_soft_deleted_spaces(): void
    {
        $user = User::factory()->create();
        Space::factory()->for($user)->count(3)->create();
        // Soft-delete one — should free a slot.
        $user->spaces()->first()->delete();

        $action = new CreateSpaceAction;

        $this->assertNull($action->checkPlanLimit($user->fresh()));
    }

    public function test_create_persists_space_and_seeds_predefined_fields(): void
    {
        $user = User::factory()->create();

        $space = (new CreateSpaceAction)->create($user, [
            'name' => 'Customer Wins',
            'title' => 'What customers say',
            'subtitle' => 'Real feedback',
            'ask' => 'What do you think?',
            'theme' => SpaceTheme::MinimalLight->value,
            'rating_enabled' => true,
        ]);

        $this->assertSame($user->id, $space->user_id);
        $this->assertSame('Customer Wins', $space->name);
        $this->assertSame('customer-wins', $space->slug);
        $this->assertNotEmpty($space->public_id);
        $this->assertSame(SpaceTheme::MinimalLight, $space->theme);
        $this->assertTrue($space->rating_enabled);

        $fields = $space->fields()->orderBy('sort_order')->get();
        $this->assertCount(3, $fields);
        $this->assertSame(['company_name', 'social_url', 'profile_photo'], $fields->pluck('field_key')->all());
        $this->assertContains($fields->first()->mode, [SpaceFieldMode::Off]);
    }

    public function test_create_appends_random_suffix_on_slug_collision(): void
    {
        $user = User::factory()->create();

        // First create with a name whose slug we can collide with.
        $existing = (new CreateSpaceAction)->create($user, [
            'name' => 'Roadmap',
            'title' => 't',
            'ask' => 'aaaaa',
            'theme' => SpaceTheme::MinimalDark->value,
            'rating_enabled' => false,
        ]);

        // Second create with the same name — slug must differ.
        $second = (new CreateSpaceAction)->create($user, [
            'name' => 'Roadmap',
            'title' => 't',
            'ask' => 'aaaaa',
            'theme' => SpaceTheme::MinimalDark->value,
            'rating_enabled' => false,
        ]);

        $this->assertSame('roadmap', $existing->slug);
        $this->assertStringStartsWith('roadmap-', $second->slug);
        $this->assertNotSame($existing->slug, $second->slug);
    }

    public function test_create_preserves_soft_deleted_slugs(): void
    {
        $user = User::factory()->create();
        $existing = (new CreateSpaceAction)->create($user, [
            'name' => 'Echo',
            'title' => 't',
            'ask' => 'aaaaa',
            'theme' => SpaceTheme::MinimalLight->value,
            'rating_enabled' => true,
        ]);
        $existing->delete(); // soft delete

        $second = (new CreateSpaceAction)->create($user, [
            'name' => 'Echo',
            'title' => 't',
            'ask' => 'aaaaa',
            'theme' => SpaceTheme::MinimalLight->value,
            'rating_enabled' => true,
        ]);

        $this->assertNotSame($existing->slug, $second->slug);
        $this->assertStringStartsWith('echo-', $second->slug);
    }

    public function test_space_field_mode_is_off_after_seed(): void
    {
        $user = User::factory()->create();
        $space = (new CreateSpaceAction)->create($user, [
            'name' => 'Quiet',
            'title' => 't',
            'ask' => 'aaaaa',
            'theme' => SpaceTheme::SoftColor->value,
            'rating_enabled' => true,
        ]);

        $this->assertTrue(
            $space->fields()->where('mode', SpaceFieldMode::Off)->exists(),
        );
        $this->assertSame(0, $space->fields()->where('mode', SpaceFieldMode::Required)->count());
    }
}
