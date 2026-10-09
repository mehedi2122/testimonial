<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP coverage for public-wall-of-love.
 *
 * Mirrors `tests/Feature/Embed/EmbedWidgetControllerTest`: route
 * shape, 200/404, body content, and a body-level security assertion
 * that the email column never escapes into the rendered HTML.
 */
class PublicWallControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_200_with_html_for_a_valid_slug(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->get(route('public.wall.show', ['slug' => $space->slug]));

        $response->assertOk();
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $response = $this->get(route('public.wall.show', ['slug' => 'zzzunknownzzz']));

        $response->assertNotFound();
    }

    public function test_show_returns_404_for_soft_deleted_space(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $space->delete();

        $response = $this->get(route('public.wall.show', ['slug' => $space->slug]));

        $response->assertNotFound();
    }

    public function test_show_body_contains_testimonial_text_and_name(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->create([
            'name' => 'Priya Iyer',
            'testimonial' => 'This product changed my workflow.',
        ]);

        $response = $this->get(route('public.wall.show', ['slug' => $space->slug]));

        $response->assertOk();
        $response->assertSee('Priya Iyer', false);
        $response->assertSee('This product changed my workflow.', false);
    }

    public function test_show_with_no_testimonials_shows_empty_state_copy(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->get(route('public.wall.show', ['slug' => $space->slug]));

        $response->assertOk();
        // The empty-state copy is rendered client-side; the server
        // emits `count: 0` and an empty testimonials array, which
        // drives the React branch to render the empty state.
        $props = $this->pageProps((string) $response->getContent());
        $this->assertSame(0, $props['count']);
        $this->assertSame([], $props['testimonials']);
    }

    public function test_show_email_is_never_in_rendered_html(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->create([
            'email' => 'super-private@example.com',
            'name' => 'Anonymous Submitter',
            'testimonial' => 'My honest review.',
        ]);

        $response = $this->get(route('public.wall.show', ['slug' => $space->slug]));

        $response->assertOk();
        $this->assertStringNotContainsString('super-private@example.com', $response->getContent());
        $this->assertStringNotContainsString('"email"', $response->getContent());
    }

    public function test_show_excludes_hidden_testimonials_from_body(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->create([
            'name' => 'Visible Author',
            'testimonial' => 'You can see this.',
        ]);
        Testimonial::factory()->for($space)->hidden()->create([
            'name' => 'Hidden Author',
            'testimonial' => 'You should not see this.',
        ]);

        $response = $this->get(route('public.wall.show', ['slug' => $space->slug]));

        $response->assertOk();
        $response->assertSee('Visible Author', false);
        $response->assertSee('You can see this.', false);
        $response->assertDontSee('Hidden Author', false);
        $response->assertDontSee('You should not see this.', false);
    }

    public function test_show_is_favorite_testimonial_emits_is_favorite_in_props(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->favorite()->create([
            'name' => 'Starred Author',
            'testimonial' => 'Pinned to the top.',
        ]);

        $response = $this->get(route('public.wall.show', ['slug' => $space->slug]));

        $response->assertOk();

        // The `<Pin />` element renders client-side; on the server
        // the payload is what the client uses, so we assert on the
        // `is_favorite` flag there. The React component consumes
        // `is_favorite` and renders the pin icon.
        $props = $this->pageProps($response->getContent());
        $this->assertSame(1, $props['count']);
        $this->assertTrue($props['testimonials'][0]['is_favorite']);
        $this->assertSame('Starred Author', $props['testimonials'][0]['name']);
    }

    /**
     * Pull the JSON props Inertia embeds in `<script data-page="app">`.
     *
     * @return array<string, mixed>
     */
    private function pageProps(string $body): array
    {
        preg_match('/<script data-page="app"[^>]*>(.*?)<\/script>/s', $body, $matches);

        if ($matches === []) {
            $this->fail('Response did not contain an Inertia data-page script.');
        }

        $decoded = json_decode($matches[1], true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('props', $decoded);

        return $decoded['props'];
    }
}
