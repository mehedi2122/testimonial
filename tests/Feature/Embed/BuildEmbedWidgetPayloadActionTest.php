<?php

declare(strict_types=1);

namespace Tests\Feature\Embed;

use App\Actions\Embeds\BuildEmbedWidgetPayloadAction;
use App\Enums\EmbedLayout;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\TestimonialValue;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Action-level coverage for embed-widget. Mirrors the other
 * Feature/Actions/*Test classes — action in isolation, no HTTP.
 */
class BuildEmbedWidgetPayloadActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_payload_for_a_space_with_default_config(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $this->assertDatabaseCount('embed_configurations', 0);

        $payload = (new BuildEmbedWidgetPayloadAction)->build($space->public_id);

        $this->assertSame($space->name, $payload['space_name']);
        $this->assertSame(EmbedLayout::Masonry->value, $payload['layout']);
        $this->assertFalse($payload['dark_mode']);
        $this->assertTrue($payload['animation_enabled']);
        $this->assertTrue($payload['show_rating']);
        $this->assertNull($payload['background_color']);
        $this->assertSame([], $payload['testimonials']);
    }

    public function test_throws_when_public_id_is_unknown(): void
    {
        $this->expectException(ModelNotFoundException::class);

        (new BuildEmbedWidgetPayloadAction)->build('zzz-unknown-zzz');
    }

    public function test_throws_when_space_is_soft_deleted(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $space->delete();

        $this->expectException(ModelNotFoundException::class);

        (new BuildEmbedWidgetPayloadAction)->build($space->public_id);
    }

    public function test_only_publicly_visible_testimonials_are_returned(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $visible = Testimonial::factory()->for($space)->create([
            'name' => 'Visible',
            'is_wall_of_love' => true,
            'is_hidden' => false,
            'consent_given' => true,
        ]);
        Testimonial::factory()->for($space)->hidden()->create(['name' => 'Hidden']);
        Testimonial::factory()->for($space)->notOnWall()->create(['name' => 'NotOnWall']);
        Testimonial::factory()->for($space)->unconsented()->create(['name' => 'NoConsent']);
        Testimonial::factory()->for($space)->create([
            'name' => 'SoftDeleted',
        ])->delete();

        $payload = (new BuildEmbedWidgetPayloadAction)->build($space->public_id);

        $this->assertCount(1, $payload['testimonials']);
        $this->assertSame('Visible', $payload['testimonials'][0]['name']);
    }

    public function test_favorites_come_before_non_favorites(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $old = Testimonial::factory()->for($space)->favorite()->create([
            'name' => 'Favorite',
            'submitted_at' => now()->subDays(30),
        ]);
        $newer = Testimonial::factory()->for($space)->create([
            'name' => 'NewerNonFav',
            'is_favorite' => false,
            'submitted_at' => now(),
        ]);

        $payload = (new BuildEmbedWidgetPayloadAction)->build($space->public_id);

        $this->assertSame('Favorite', $payload['testimonials'][0]['name']);
        $this->assertSame('NewerNonFav', $payload['testimonials'][1]['name']);
    }

    public function test_respects_item_limit_from_config(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        EmbedConfiguration::factory()->for($space)->create(['item_limit' => 3]);
        for ($i = 0; $i < 5; $i++) {
            Testimonial::factory()->for($space)->create();
        }

        $payload = (new BuildEmbedWidgetPayloadAction)->build($space->public_id);

        $this->assertCount(3, $payload['testimonials']);
    }

    public function test_clamps_item_limit_above_max(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        EmbedConfiguration::factory()->for($space)->create([
            'item_limit' => 1000,
        ]);
        for ($i = 0; $i < 60; $i++) {
            Testimonial::factory()->for($space)->create();
        }

        $payload = (new BuildEmbedWidgetPayloadAction)->build($space->public_id);

        $this->assertCount(EmbedConfiguration::MAX_ITEM_LIMIT, $payload['testimonials']);
    }

    public function test_email_is_never_in_any_testimonial_payload(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->create([
            'email' => 'private@example.com',
        ]);

        $payload = (new BuildEmbedWidgetPayloadAction)->build($space->public_id);

        $serialized = json_encode($payload);
        $this->assertStringNotContainsString('private@example.com', $serialized);
        $this->assertStringNotContainsString('email', $serialized);
    }

    public function test_respects_space_fields_show_in_embed(): void
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

        $payload = (new BuildEmbedWidgetPayloadAction)->build($space->public_id);

        $fields = $payload['testimonials'][0]['fields'];
        $keys = array_column($fields, 'label');

        $this->assertNotContains('Company name', $keys);
        $this->assertContains('Social profile URL', $keys);
    }

    public function test_uses_saved_config_values(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        EmbedConfiguration::factory()->for($space)->create([
            'layout' => EmbedLayout::Carousel,
            'dark_mode' => true,
            'animation_enabled' => false,
            'show_rating' => false,
            'background_color' => '#0F172A',
            'item_limit' => 24,
        ]);

        $payload = (new BuildEmbedWidgetPayloadAction)->build($space->public_id);

        $this->assertSame('carousel', $payload['layout']);
        $this->assertTrue($payload['dark_mode']);
        $this->assertFalse($payload['animation_enabled']);
        $this->assertFalse($payload['show_rating']);
        $this->assertSame('#0F172A', $payload['background_color']);
    }

    public function test_submitted_at_is_iso8601(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->create([
            'submitted_at' => '2026-09-14 12:00:00',
        ]);

        $payload = (new BuildEmbedWidgetPayloadAction)->build($space->public_id);

        $this->assertSame('2026-09-14T12:00:00+00:00', $payload['testimonials'][0]['submitted_at']);
    }
}
