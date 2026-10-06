<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Public\ShowPublicSubmissionAction;
use App\Enums\SpaceFieldMode;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Action-level tests for the public submission form resolver (OpenSpec
 * change: public-submission-form). Mirrors the style of
 * CreateSpaceActionTest — class-based, no controller.
 */
class ShowPublicSubmissionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_returns_live_space_with_visible_fields(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        // Promote one of the predefined fields out of Off so it shows up.
        $space->fields()->where('field_key', 'company_name')->update([
            'mode' => SpaceFieldMode::Required,
        ]);

        $resolved = (new ShowPublicSubmissionAction)->resolve($space->public_id);

        $this->assertSame($space->id, $resolved->id);
        $this->assertSame($space->public_id, $resolved->public_id);

        // Predefined rows that are still Off must NOT appear.
        $keys = $resolved->fields->pluck('field_key')->all();
        $this->assertContains('company_name', $keys);
        $this->assertNotContains('social_url', $keys);
        $this->assertNotContains('profile_photo', $keys);
    }

    public function test_resolve_orders_fields_by_sort_order(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        // Promote all three, then update their sort_orders out of sequence.
        $space->fields()->update(['mode' => SpaceFieldMode::Optional]);
        $space->fields()->where('field_key', 'company_name')->update(['sort_order' => 30]);
        $space->fields()->where('field_key', 'social_url')->update(['sort_order' => 10]);
        $space->fields()->where('field_key', 'profile_photo')->update(['sort_order' => 20]);

        $resolved = (new ShowPublicSubmissionAction)->resolve($space->public_id);

        $this->assertSame(
            ['social_url', 'profile_photo', 'company_name'],
            $resolved->fields->pluck('field_key')->all(),
        );
    }

    public function test_resolve_throws_on_soft_deleted_space(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $space->delete();

        $this->expectException(ModelNotFoundException::class);

        (new ShowPublicSubmissionAction)->resolve($space->public_id);
    }

    public function test_resolve_throws_on_unknown_public_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        (new ShowPublicSubmissionAction)->resolve('does-not-exist');
    }

    public function test_resolve_works_with_factory_direct_creation(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        // Sanity check the factory seeded the predefined fields.
        $this->assertSame(3, $space->fields()->count());
    }
}
