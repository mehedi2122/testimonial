<?php

declare(strict_types=1);

namespace Tests\Feature\Embed;

use App\Enums\EmbedLayout;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP coverage for the embed-widget OpenSpec change.
 *
 * The frame route is the public surface — anyone can hit
 * `/embed/{public_id}` and get an iframe document. The tests cover
 * the 200, 404, override, and loader cases.
 */
class EmbedWidgetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_frame_returns_200_with_html(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->get(route('embed.frame', ['publicId' => $space->public_id]));

        $response->assertOk();
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
        $response->assertSee('<!DOCTYPE html>', false);
    }

    public function test_frame_returns_404_for_unknown_public_id(): void
    {
        $response = $this->get(route('embed.frame', ['publicId' => 'zzzunknownzzz']));

        $response->assertNotFound();
    }

    public function test_frame_returns_404_for_soft_deleted_space(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $space->delete();

        $response = $this->get(route('embed.frame', ['publicId' => $space->public_id]));

        $response->assertNotFound();
    }

    public function test_loader_returns_200_with_javascript_content_type(): void
    {
        $response = $this->get(route('embed.loader'));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/javascript',
            (string) $response->headers->get('Content-Type'),
        );

        // BinaryFileResponse streams the file; getContent() returns
        // ''. Read the file directly to verify the loader content.
        $this->assertStringContainsString(
            'data-testimonial-space',
            (string) file_get_contents(public_path('embed.js')),
        );
    }

    public function test_frame_body_contains_testimonial_text(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->create([
            'name' => 'Priya Iyer',
            'testimonial' => 'This product changed my workflow.',
        ]);

        $response = $this->get(route('embed.frame', ['publicId' => $space->public_id]));

        $response->assertOk();
        $response->assertSee('Priya Iyer', false);
        $response->assertSee('This product changed my workflow.', false);
    }

    public function test_frame_respects_style_query_override(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->create();

        $response = $this->get(
            route('embed.frame', ['publicId' => $space->public_id]).'?style=carousel',
        );

        $response->assertOk();
        $response->assertSee('class="layout-carousel"', false);
    }

    public function test_frame_respects_show_rating_query_override(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->create(['rating' => 5]);

        $response = $this->get(
            route('embed.frame', ['publicId' => $space->public_id]).'?show-rating=0',
        );

        $response->assertOk();
        $response->assertDontSee('aria-label="5 out of 5"', false);
    }

    public function test_frame_with_no_testimonials_shows_empty_state(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->get(route('embed.frame', ['publicId' => $space->public_id]));

        $response->assertOk();
        $response->assertSee('No testimonials yet.', false);
    }

    public function test_frame_uses_saved_layout_class(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        EmbedConfiguration::factory()->for($space)->create([
            'layout' => EmbedLayout::Carousel,
        ]);
        Testimonial::factory()->for($space)->create();

        $response = $this->get(route('embed.frame', ['publicId' => $space->public_id]));

        $response->assertOk();
        $response->assertSee('class="layout-carousel"', false);
    }

    public function test_frame_email_never_in_html_body(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->create([
            'email' => 'super-private@example.com',
            'name' => 'Anonymous Submitter',
            'testimonial' => 'My honest review.',
        ]);

        $response = $this->get(route('embed.frame', ['publicId' => $space->public_id]));

        $response->assertOk();
        $this->assertStringNotContainsString('super-private@example.com', $response->getContent());
        $this->assertStringNotContainsString('"email"', $response->getContent());
    }

    public function test_frame_applies_background_color_to_the_page_background_variable(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        EmbedConfiguration::factory()->for($space)->create([
            'background_color' => '#0F172A',
        ]);

        $response = $this->get(route('embed.frame', ['publicId' => $space->public_id]));

        $response->assertOk();
        // body paints var(--bg), so the color must land on the variable,
        // not on <html>'s own background (which body would cover).
        $response->assertSee('style="--bg: #0F172A;"', false);
    }

    public function test_frame_disables_animation_when_config_turns_it_off(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        EmbedConfiguration::factory()->for($space)->create([
            'animation_enabled' => false,
        ]);

        $response = $this->get(route('embed.frame', ['publicId' => $space->public_id]));

        $response->assertOk();
        $response->assertSee('class="no-animation"', false);
    }

    public function test_frame_keeps_animation_when_config_enables_it(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        EmbedConfiguration::factory()->for($space)->create([
            'animation_enabled' => true,
        ]);

        $response = $this->get(route('embed.frame', ['publicId' => $space->public_id]));

        $response->assertOk();
        $response->assertDontSee('class="no-animation"', false);
    }

    public function test_frame_respects_animation_query_override(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->get(
            route('embed.frame', ['publicId' => $space->public_id]).'?animation=0',
        );

        $response->assertOk();
        $response->assertSee('class="no-animation"', false);
    }

    public function test_frame_reports_its_height_to_the_loader(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->get(route('embed.frame', ['publicId' => $space->public_id]));

        $response->assertOk();
        $response->assertSee('testimonial-embed:resize', false);
        $this->assertStringContainsString(
            'testimonial-embed:resize',
            (string) file_get_contents(public_path('embed.js')),
        );
    }
}
