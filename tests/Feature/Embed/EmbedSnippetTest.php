<?php

declare(strict_types=1);

namespace Tests\Feature\Embed;

use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\User;
use App\Support\EmbedSnippet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The snippet is the contract between the embed builder and the public
 * loader: every option the frame honours must be carried on the div.
 */
class EmbedSnippetTest extends TestCase
{
    use RefreshDatabase;

    public function test_snippet_carries_animation_flag(): void
    {
        $space = Space::factory()->for(User::factory())->create();
        $config = EmbedConfiguration::factory()->for($space)->create([
            'animation_enabled' => false,
        ]);

        $snippet = EmbedSnippet::for($space, $config);

        $this->assertStringContainsString('data-animation="0"', $snippet);
    }

    public function test_snippet_versions_the_loader_script(): void
    {
        $space = Space::factory()->for(User::factory())->create();
        $config = EmbedConfiguration::factory()->for($space)->create();

        $snippet = EmbedSnippet::for($space, $config);

        $this->assertMatchesRegularExpression('#/embed\.js\?v=\d+" defer></script>#', $snippet);
    }
}
