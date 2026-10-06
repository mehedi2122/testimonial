<?php

declare(strict_types=1);

use App\Models\Space as SpaceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('routes /s/{public_id}/submissions to the public submission controller', function (): void {
    $space = SpaceModel::factory()->create();

    $response = $this->postJson("/s/{$space->public_id}/submissions");

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
    $response = $this->postJson('/s/zzz-unknown-zzz/submissions');

    $response->assertStatus(404);
    $response->assertJson(['error' => 'space_not_found']);
});

it('returns 404 space_not_found for a soft-deleted Space', function (): void {
    $space = SpaceModel::factory()->create();
    $space->delete();

    $response = $this->postJson("/s/{$space->public_id}/submissions");

    $response->assertStatus(404);
    $response->assertJson(['error' => 'space_not_found']);
});
