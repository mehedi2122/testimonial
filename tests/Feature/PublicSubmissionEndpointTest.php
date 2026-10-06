<?php

declare(strict_types=1);

use App\Enums\Plan;
use App\Models\Space as SpaceModel;
use App\Models\Testimonial;
use App\Models\TestimonialValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Group 1: routing + SoftDeletes preflight.

it('routes /s/{public_id}/submissions to the public submission controller', function (): void {
    $space = SpaceModel::factory()->create();

    $response = $this->postJson("/s/{$space->public_id}/submissions", validPayload());

    $response->assertStatus(201);
    $response->assertJsonStructure(['ok', 'testimonial' => ['id', 'submitted_at']]);
});

it('returns 404 space_not_found for an unknown public_id', function (): void {
    $response = $this->postJson('/s/zzz-unknown-zzz/submissions', validPayload());

    $response->assertStatus(404);
    $response->assertJson(['error' => 'space_not_found']);
});

it('returns 404 space_not_found for a soft-deleted Space', function (): void {
    $space = SpaceModel::factory()->create();
    $space->delete();

    $response = $this->postJson("/s/{$space->public_id}/submissions", validPayload());

    $response->assertStatus(404);
    $response->assertJson(['error' => 'space_not_found']);
});

// Group 2: FormRequest base validation.

it('rejects submissions missing name', function (): void {
    $space = SpaceModel::factory()->create();

    $payload = validPayload();
    unset($payload['name']);

    $response = $this->postJson("/s/{$space->public_id}/submissions", $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

it('rejects submissions missing email', function (): void {
    $space = SpaceModel::factory()->create();

    $payload = validPayload();
    unset($payload['email']);

    $response = $this->postJson("/s/{$space->public_id}/submissions", $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

it('rejects submissions with a malformed email', function (): void {
    $space = SpaceModel::factory()->create();

    $payload = validPayload();
    $payload['email'] = 'not-an-email';

    $response = $this->postJson("/s/{$space->public_id}/submissions", $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

it('rejects submissions missing testimonial', function (): void {
    $space = SpaceModel::factory()->create();

    $payload = validPayload();
    unset($payload['testimonial']);

    $response = $this->postJson("/s/{$space->public_id}/submissions", $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['testimonial']);
});

it('requires rating when the Space has rating_enabled', function (): void {
    $space = SpaceModel::factory()->create(['rating_enabled' => true]);

    $payload = validPayload();
    unset($payload['rating']);

    $response = $this->postJson("/s/{$space->public_id}/submissions", $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['rating']);
});

it('does not require rating when the Space has rating_enabled false', function (): void {
    $space = SpaceModel::factory()->create(['rating_enabled' => false]);

    $payload = validPayload();
    unset($payload['rating']);

    $response = $this->postJson("/s/{$space->public_id}/submissions", $payload);

    $response->assertStatus(201);
});

it('rejects consent_given = false', function (): void {
    $space = SpaceModel::factory()->create();

    $payload = validPayload();
    $payload['consent_given'] = false;

    $response = $this->postJson("/s/{$space->public_id}/submissions", $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['consent_given']);
});

it('accepts a values array with field_key + value entries', function (): void {
    $space = SpaceModel::factory()->create();

    $payload = validPayload();
    $payload['values'] = [
        ['field_key' => 'company_name', 'value' => 'Acme Corp'],
        ['field_key' => 'social_url', 'value' => 'https://example.com'],
    ];

    $response = $this->postJson("/s/{$space->public_id}/submissions", $payload);

    $response->assertStatus(201);
});

it('rejects values entries missing field_key', function (): void {
    $space = SpaceModel::factory()->create();

    $payload = validPayload();
    $payload['values'] = [
        ['value' => 'Acme Corp'],
    ];

    $response = $this->postJson("/s/{$space->public_id}/submissions", $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['values.0.field_key']);
});

it('rejects values entries with reserved field keys', function (): void {
    $space = SpaceModel::factory()->create();

    $payload = validPayload();
    $payload['values'] = [
        ['field_key' => 'email', 'value' => 'someone@example.com'],
    ];

    $response = $this->postJson("/s/{$space->public_id}/submissions", $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['values.0.field_key']);
});

it('rejects reserved field keys case-insensitively', function (): void {
    $space = SpaceModel::factory()->create();

    $payload = validPayload();
    $payload['values'] = [
        ['field_key' => 'NAME', 'value' => 'whatever'],
    ];

    $response = $this->postJson("/s/{$space->public_id}/submissions", $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['values.0.field_key']);
});

// Group 7: plan-limit check.

it('rejects with 422 limit_reached when a Free-plan Space is at 100/100', function (): void {
    $owner = User::factory()->create();
    $space = SpaceModel::factory()->for($owner)->create();

    Testimonial::factory()
        ->count(Plan::Free->maxTestimonialsPerSpace())
        ->for($space)
        ->create();

    $response = $this->postJson("/s/{$space->public_id}/submissions", validPayload());

    $response->assertStatus(422);
    $response->assertExactJson([
        'error' => 'limit_reached',
        'plan' => Plan::Free->value,
        'limit' => Plan::Free->maxTestimonialsPerSpace(),
    ]);
});

it('rejects with 422 limit_reached when a Free-plan Space is over the cap', function (): void {
    $owner = User::factory()->create();
    $space = SpaceModel::factory()->for($owner)->create();

    Testimonial::factory()
        ->count(Plan::Free->maxTestimonialsPerSpace() + 5)
        ->for($space)
        ->create();

    $response = $this->postJson("/s/{$space->public_id}/submissions", validPayload());

    $response->assertStatus(422);
    $response->assertJson(['error' => 'limit_reached']);
});

it('counts only live testimonials toward the limit (soft-deleted are excluded)', function (): void {
    $owner = User::factory()->create();
    $space = SpaceModel::factory()->for($owner)->create();

    // 99 live + 5 soft-deleted = still under the 100 cap.
    Testimonial::factory()
        ->count(99)
        ->for($space)
        ->create();
    Testimonial::factory()
        ->count(5)
        ->for($space)
        ->create()
        ->each(fn (Testimonial $t) => $t->delete());

    $response = $this->postJson("/s/{$space->public_id}/submissions", validPayload());

    $response->assertStatus(201);
});

it('accepts a Pro-plan Space at 99/1000 with 1 spot left', function (): void {
    $owner = User::factory()->create();
    $owner->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_pro',
        'quantity' => 1,
    ]);
    $space = SpaceModel::factory()->for($owner)->create();

    Testimonial::factory()
        ->count(Plan::Pro->maxTestimonialsPerSpace() - 1)
        ->for($space)
        ->create();

    $response = $this->postJson("/s/{$space->public_id}/submissions", validPayload());

    $response->assertStatus(201);
});

it('rejects a Pro-plan Space at 1000/1000', function (): void {
    $owner = User::factory()->create();
    $owner->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_pro',
        'quantity' => 1,
    ]);
    $space = SpaceModel::factory()->for($owner)->create();

    Testimonial::factory()
        ->count(Plan::Pro->maxTestimonialsPerSpace())
        ->for($space)
        ->create();

    $response = $this->postJson("/s/{$space->public_id}/submissions", validPayload());

    $response->assertStatus(422);
    $response->assertExactJson([
        'error' => 'limit_reached',
        'plan' => Plan::Pro->value,
        'limit' => Plan::Pro->maxTestimonialsPerSpace(),
    ]);
});

// Group 8: atomic Testimonial + TestimonialValue[] create.

it('persists a Testimonial row on success', function (): void {
    $space = SpaceModel::factory()->create();

    $this->postJson("/s/{$space->public_id}/submissions", validPayload())->assertStatus(201);

    expect(Testimonial::query()->where('space_id', $space->id)->count())->toBe(1);
});

it('persists one TestimonialValue row per values entry', function (): void {
    $space = SpaceModel::factory()->create();

    $payload = validPayload();
    $payload['values'] = [
        ['field_key' => 'company_name', 'value' => 'Acme Corp'],
        ['field_key' => 'social_url', 'value' => 'https://example.com'],
    ];

    $this->postJson("/s/{$space->public_id}/submissions", $payload)->assertStatus(201);

    $testimonial = Testimonial::query()->where('space_id', $space->id)->firstOrFail();
    expect($testimonial->values()->count())->toBe(2);
    expect(TestimonialValue::query()->where('testimonial_id', $testimonial->id)->count())->toBe(2);
});

it('rolls back when a TestimonialValue insert fails mid-transaction', function (): void {
    $space = SpaceModel::factory()->create();

    // Force the second insert (TestimonialValue) to throw. The transaction
    // wrapping the create() call must roll back the Testimonial row too.
    TestimonialValue::creating(function (TestimonialValue $v): void {
        throw new RuntimeException('simulated mid-transaction failure');
    });

    $payload = validPayload();
    $payload['values'] = [
        ['field_key' => 'company_name', 'value' => 'Acme Corp'],
    ];

    $response = $this->postJson("/s/{$space->public_id}/submissions", $payload);

    // The exception bubbles to the framework as 500 — what matters is
    // that no row survived.
    expect($response->status())->toBeIn([500, 503]);
    expect(Testimonial::query()->where('space_id', $space->id)->count())->toBe(0);
    expect(TestimonialValue::query()->count())->toBe(0);
});

it('commits Testimonial + TestimonialValue[] together on success', function (): void {
    $space = SpaceModel::factory()->create();

    $payload = validPayload();
    $payload['values'] = [
        ['field_key' => 'company_name', 'value' => 'Acme Corp'],
    ];

    $this->postJson("/s/{$space->public_id}/submissions", $payload)->assertStatus(201);

    $testimonial = Testimonial::query()->where('space_id', $space->id)->firstOrFail();
    expect($testimonial->values()->count())->toBe(1);
    expect($testimonial->values()->first()->value)->toBe('Acme Corp');
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * @return array<string, mixed>
 */
function validPayload(): array
{
    return [
        'name' => 'Priya Raman',
        'email' => 'priya@example.com',
        'testimonial' => 'Acme saved us hours every week.',
        'rating' => 5,
        'consent_given' => true,
    ];
}
