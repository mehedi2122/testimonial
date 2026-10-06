<?php

declare(strict_types=1);

use App\Models\Space as SpaceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Group 1: routing + SoftDeletes preflight.

it('routes /s/{public_id}/submissions to the public submission controller', function (): void {
    $space = SpaceModel::factory()->create();

    $response = $this->postJson("/s/{$space->public_id}/submissions", validPayload());

    $response->assertStatus(201);
    $response->assertJson([
        'ok' => true,
        'space' => [
            'id' => $space->id,
            'name' => $space->name,
        ],
    ]);
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
