<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Authorization for the four Space policy methods. OpenSpec change
 * embed-builder adds `updateEmbed`; the other three were already in
 * place but never had explicit unit coverage — locking the rule for all
 * four in one place makes future surface additions a one-method add.
 */
class SpacePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $this->assertTrue($user->can('view', $space));
    }

    public function test_non_owner_cannot_view(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($owner)->create();

        $this->assertFalse($other->can('view', $space));
    }

    public function test_owner_can_update(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $this->assertTrue($user->can('update', $space));
    }

    public function test_non_owner_cannot_update(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($owner)->create();

        $this->assertFalse($other->can('update', $space));
    }

    public function test_owner_can_delete(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $this->assertTrue($user->can('delete', $space));
    }

    public function test_non_owner_cannot_delete(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($owner)->create();

        $this->assertFalse($other->can('delete', $space));
    }

    public function test_owner_can_update_embed(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $this->assertTrue($user->can('updateEmbed', $space));
    }

    public function test_non_owner_cannot_update_embed(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($owner)->create();

        $this->assertFalse($other->can('updateEmbed', $space));
    }
}
