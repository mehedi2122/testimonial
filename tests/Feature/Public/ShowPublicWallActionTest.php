<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Actions\Public\ShowPublicWallAction;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\TestimonialValue;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Action-level coverage for public-wall-of-love.
 *
 * Mirrors `tests/Feature/Embed/BuildEmbedWidgetPayloadActionTest`:
 * action in isolation, no HTTP. The wall has no embed_configurations
 * row to read; the test surface focuses on `resolve()` and the
 * `scopePubliclyVisible()`-based projection.
 */
class ShowPublicWallActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_returns_the_space_for_a_valid_slug(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create(['slug' => 'test-wall']);

        $resolved = (new ShowPublicWallAction)->resolve('test-wall');

        $this->assertSame($space->id, $resolved->id);
        $this->assertSame('test-wall', $resolved->slug);
    }

    public function test_resolve_throws_for_unknown_slug(): void
    {
        $this->expectException(ModelNotFoundException::class);

        (new ShowPublicWallAction)->resolve('zzz-unknown-zzz');
    }

    public function test_resolve_throws_for_soft_deleted_space(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create(['slug' => 'trashed']);
        $space->delete();

        $this->expectException(ModelNotFoundException::class);

        (new ShowPublicWallAction)->resolve('trashed');
    }

    public function test_build_payload_returns_only_publicly_visible_testimonials(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        Testimonial::factory()->for($space)->create(['name' => 'Visible']);
        Testimonial::factory()->for($space)->hidden()->create(['name' => 'Hidden']);
        Testimonial::factory()->for($space)->notOnWall()->create(['name' => 'NotOnWall']);
        Testimonial::factory()->for($space)->unconsented()->create(['name' => 'NoConsent']);
        Testimonial::factory()->for($space)->create(['name' => 'SoftDeleted'])->delete();

        $payload = (new ShowPublicWallAction)->buildPayload($space);

        $this->assertSame(1, $payload['count']);
        $this->assertCount(1, $payload['testimonials']);
        $this->assertSame('Visible', $payload['testimonials'][0]['name']);
    }

    public function test_build_payload_orders_favorites_before_newer_non_favorites(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        Testimonial::factory()->for($space)->favorite()->create([
            'name' => 'OlderFavorite',
            'submitted_at' => now()->subDays(30),
        ]);
        Testimonial::factory()->for($space)->create([
            'name' => 'NewerNonFav',
            'is_favorite' => false,
            'submitted_at' => now(),
        ]);

        $payload = (new ShowPublicWallAction)->buildPayload($space);

        $this->assertSame('OlderFavorite', $payload['testimonials'][0]['name']);
        $this->assertSame('NewerNonFav', $payload['testimonials'][1]['name']);
        $this->assertTrue($payload['testimonials'][0]['is_favorite']);
        $this->assertFalse($payload['testimonials'][1]['is_favorite']);
    }

    public function test_build_payload_includes_is_favorite_for_each_testimonial(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->favorite()->create();
        Testimonial::factory()->for($space)->create(['is_favorite' => false]);

        $payload = (new ShowPublicWallAction)->buildPayload($space);

        $this->assertCount(2, $payload['testimonials']);
        $favorites = array_column($payload['testimonials'], 'is_favorite');
        $this->assertContains(true, $favorites);
        $this->assertContains(false, $favorites);
    }

    public function test_build_payload_email_is_never_in_any_testimonial_payload(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->create([
            'email' => 'private@example.com',
        ]);

        $payload = (new ShowPublicWallAction)->buildPayload($space);

        $serialized = json_encode($payload);
        $this->assertStringNotContainsString('private@example.com', $serialized);
        $this->assertStringNotContainsString('email', $serialized);
    }

    public function test_build_payload_respects_space_fields_show_in_embed(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        // The 3 predefined fields are seeded at show_in_embed=true. Flip one off.
        $space->fields()->where('field_key', 'company_name')->update(['show_in_embed' => false]);

        $testimonial = Testimonial::factory()->for($space)->create();
        $companyField = $space->fields()->where('field_key', 'company_name')->first();
        TestimonialValue::factory()->create([
            'testimonial_id' => $testimonial->id,
            'space_field_id' => $companyField->id,
            'value' => 'Acme Inc',
        ]);
        $socialField = $space->fields()->where('field_key', 'social_url')->first();
        TestimonialValue::factory()->create([
            'testimonial_id' => $testimonial->id,
            'space_field_id' => $socialField->id,
            'value' => 'https://example.com/p',
        ]);

        $payload = (new ShowPublicWallAction)->buildPayload($space);

        $fields = $payload['testimonials'][0]['fields'];
        $keys = array_column($fields, 'label');

        $this->assertNotContains('Company name', $keys);
        $this->assertContains('Social profile URL', $keys);
    }

    public function test_build_payload_for_empty_space(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $payload = (new ShowPublicWallAction)->buildPayload($space);

        $this->assertSame(0, $payload['count']);
        $this->assertSame([], $payload['testimonials']);
        $this->assertSame($space->name, $payload['space']['name']);
        $this->assertSame($space->title, $payload['space']['title']);
    }

    public function test_build_payload_payload_shape_includes_space_metadata(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create([
            'name' => 'Brightcopy Wins',
            'title' => 'Brightcopy Client Wins',
            'subtitle' => 'Real words from real customers.',
        ]);

        $payload = (new ShowPublicWallAction)->buildPayload($space);

        $this->assertSame('Brightcopy Wins', $payload['space']['name']);
        $this->assertSame('Brightcopy Client Wins', $payload['space']['title']);
        $this->assertSame('Real words from real customers.', $payload['space']['subtitle']);
        $this->assertArrayHasKey('theme', $payload['space']);
    }

    public function test_build_payload_does_not_read_embed_configurations(): void
    {
        // Defence in depth: the wall must NOT depend on embed_configurations.
        // We seed a row but the action should ignore it; only count and
        // ordering should reflect the testimonials themselves.
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        EmbedConfiguration::factory()->for($space)->create(['item_limit' => 1]);
        for ($i = 0; $i < 5; $i++) {
            Testimonial::factory()->for($space)->create();
        }

        $payload = (new ShowPublicWallAction)->buildPayload($space);

        // Wall shows the full public set; item_limit is embed-only.
        $this->assertCount(5, $payload['testimonials']);
    }
}
