<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Actions\Photos\StoreTestimonialPhotoAction;
use App\Enums\SpaceFieldMode;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\TestimonialValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Profile photo upload + serving (PRD §15, §31).
 */
class TestimonialPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_photo_is_reencoded_to_a_private_jpeg(): void
    {
        $space = $this->spaceWithPhotoField();

        $this->post("/s/{$space->public_id}/submissions", [
            ...$this->payload(),
            'photos' => ['profile_photo' => UploadedFile::fake()->image('me.png', 2000, 1000)],
        ], ['Accept' => 'application/json'])->assertCreated();

        $value = $this->photoValue($space);
        $this->assertTrue(StoreTestimonialPhotoAction::isStoredPath($value->value));
        Storage::disk('local')->assertExists($value->value);

        $info = getimagesizefromstring(Storage::disk('local')->get($value->value));
        $this->assertNotFalse($info);
        // JPEG where GD has the codec, PNG otherwise — never the original.
        $this->assertContains($info['mime'], ['image/jpeg', 'image/png']);
        $this->assertSame(512, $info[0]); // longest edge capped
        $this->assertSame(256, $info[1]);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $space = $this->spaceWithPhotoField();

        $this->post("/s/{$space->public_id}/submissions", [
            ...$this->payload(),
            'photos' => ['profile_photo' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php')],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photos.profile_photo']);

        $this->assertSame(0, Testimonial::query()->count());
    }

    public function test_oversized_photo_is_rejected(): void
    {
        $space = $this->spaceWithPhotoField();

        $this->post("/s/{$space->public_id}/submissions", [
            ...$this->payload(),
            'photos' => ['profile_photo' => UploadedFile::fake()->image('big.png')->size(3000)],
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_disguised_file_is_rejected_and_nothing_is_stored(): void
    {
        $space = $this->spaceWithPhotoField();
        $fake = UploadedFile::fake()->createWithContent('photo.jpg', "\xFF\xD8\xFF garbage that is not a real jpeg");

        $this->post("/s/{$space->public_id}/submissions", [
            ...$this->payload(),
            'photos' => ['profile_photo' => $fake],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(0, Testimonial::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_photo_for_a_disabled_field_is_rejected(): void
    {
        $space = Space::factory()->for(User::factory())->create(); // profile_photo stays off

        $this->post("/s/{$space->public_id}/submissions", [
            ...$this->payload(),
            'photos' => ['profile_photo' => UploadedFile::fake()->image('me.png')],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photos.profile_photo']);
    }

    public function test_required_photo_must_be_uploaded(): void
    {
        $space = $this->spaceWithPhotoField(SpaceFieldMode::Required);

        $this->postJson("/s/{$space->public_id}/submissions", $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.profile_photo']);
    }

    public function test_owner_can_view_any_photo(): void
    {
        [$space, $value] = $this->submittedPhoto(public: false);

        $this->actingAs($space->user)
            ->get(route('photos.show', ['value' => $value->id]))
            ->assertOk()
            ->assertHeader('Content-Type', StoreTestimonialPhotoAction::contentType($value->value))
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_public_photo_is_visible_to_anyone(): void
    {
        [, $value] = $this->submittedPhoto(public: true);

        $this->get(route('photos.show', ['value' => $value->id]))->assertOk();
    }

    public function test_photo_of_a_non_public_testimonial_is_hidden_from_others(): void
    {
        [, $value] = $this->submittedPhoto(public: false);

        $this->get(route('photos.show', ['value' => $value->id]))->assertNotFound();
        $this->actingAs(User::factory()->create())
            ->get(route('photos.show', ['value' => $value->id]))
            ->assertNotFound();
    }

    public function test_hidden_testimonial_photo_is_not_public(): void
    {
        [, $value] = $this->submittedPhoto(public: true);
        $value->testimonial->update(['is_hidden' => true]);

        $this->get(route('photos.show', ['value' => $value->id]))->assertNotFound();
    }

    public function test_photo_is_not_public_when_the_field_is_hidden_from_embeds(): void
    {
        [$space, $value] = $this->submittedPhoto(public: true);
        $space->fields()->where('field_key', 'profile_photo')->update(['show_in_embed' => false]);

        $this->get(route('photos.show', ['value' => $value->id]))->assertNotFound();
    }

    public function test_a_value_that_is_not_a_stored_photo_path_is_never_served(): void
    {
        $space = $this->spaceWithPhotoField();
        $testimonial = Testimonial::factory()->for($space)->create(['consent_given' => true, 'is_wall_of_love' => true]);
        $value = TestimonialValue::query()->create([
            'testimonial_id' => $testimonial->id,
            'space_field_id' => $space->fields()->where('field_key', 'profile_photo')->value('id'),
            'value' => '../../.env',
        ]);

        $this->actingAs($space->user)
            ->get(route('photos.show', ['value' => $value->id]))
            ->assertNotFound();
    }

    public function test_wall_payload_exposes_photo_url_not_the_storage_path(): void
    {
        [$space, $value] = $this->submittedPhoto(public: true);

        $this->get(route('public.wall.show', ['slug' => $space->slug]))
            ->assertInertia(fn ($page) => $page
                ->where('testimonials.0.photo_url', route('photos.show', ['value' => $value->id]))
                ->where('testimonials.0.fields', fn ($fields) => collect($fields)->where('type', 'image')->isEmpty())
            );
    }

    private function spaceWithPhotoField(SpaceFieldMode $mode = SpaceFieldMode::Optional): Space
    {
        $space = Space::factory()->for(User::factory())->create();
        $space->fields()->where('field_key', 'profile_photo')->update(['mode' => $mode]);

        return $space;
    }

    /**
     * @return array{0: Space, 1: TestimonialValue}
     */
    private function submittedPhoto(bool $public): array
    {
        $space = $this->spaceWithPhotoField();

        $this->post("/s/{$space->public_id}/submissions", [
            ...$this->payload(),
            'consent_given' => '1',
            'photos' => ['profile_photo' => UploadedFile::fake()->image('me.png', 300, 300)],
        ], ['Accept' => 'application/json'])->assertCreated();

        $value = $this->photoValue($space);

        if ($public) {
            $value->testimonial->update(['is_wall_of_love' => true]);
        }

        return [$space, $value->fresh() ?? $value];
    }

    private function photoValue(Space $space): TestimonialValue
    {
        return TestimonialValue::query()
            ->where('space_field_id', $space->fields()->where('field_key', 'profile_photo')->value('id'))
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'Priya Raman',
            'email' => 'priya@example.com',
            'testimonial' => 'Acme saved us hours every week.',
            'rating' => 5,
            'values' => [['field_key' => 'address', 'value' => '1 Main St']],
        ];
    }
}
