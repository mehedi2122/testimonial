<?php

declare(strict_types=1);

use App\Actions\SubmitTestimonialAction;
use App\Models\Space as SpaceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper: invoke the consent gate and return its result (or null).
 *
 * @param  array<string, mixed>  $payload
 * @return array{error: string}|null
 */
function runConsentGate(array $payload): ?array
{
    return (new SubmitTestimonialAction)->checkConsentForWallOfLove($payload);
}

it('returns null when is_wall_of_love is false regardless of consent', function (): void {
    expect(runConsentGate(['is_wall_of_love' => false, 'consent_given' => false]))->toBeNull();
    expect(runConsentGate(['is_wall_of_love' => false, 'consent_given' => true]))->toBeNull();
});

it('returns null when is_wall_of_love is true and consent_given is true', function (): void {
    expect(runConsentGate(['is_wall_of_love' => true, 'consent_given' => true]))->toBeNull();
});

it('returns consent_required when is_wall_of_love is true and consent_given is false', function (): void {
    $result = runConsentGate(['is_wall_of_love' => true, 'consent_given' => false]);

    expect($result)->toBe(['error' => 'consent_required']);
});

it('treats a missing is_wall_of_love as false', function (): void {
    expect(runConsentGate(['consent_given' => false]))->toBeNull();
});

it('treats a missing consent_given as false', function (): void {
    expect(runConsentGate(['is_wall_of_love' => true]))->toBe(['error' => 'consent_required']);
});

// Group 10: htmlspecialchars sanitization on write.

it('strips script tags from name when persisted', function (): void {
    $space = SpaceModel::factory()->create();
    $action = new SubmitTestimonialAction;

    $testimonial = $action->create($space, [
        'name' => '<script>alert(1)</script>Priya',
        'email' => 'priya@example.com',
        'testimonial' => 'Great product, very helpful.',
        'consent_given' => true,
    ]);

    expect($testimonial->name)->not->toContain('<script>');
    expect($testimonial->name)->toContain('Priya');
});

it('strips script tags from testimonial when persisted', function (): void {
    $space = SpaceModel::factory()->create();
    $action = new SubmitTestimonialAction;

    $testimonial = $action->create($space, [
        'name' => 'Priya',
        'email' => 'priya@example.com',
        'testimonial' => 'Hello <img src=x onerror=alert(1)> world',
        'consent_given' => true,
    ]);

    // The raw '<' is the hazard — htmlspecialchars turns it into '&lt;'
    // so the browser cannot interpret the rest of the string as a tag.
    expect($testimonial->testimonial)->not->toContain('<img');
    expect($testimonial->testimonial)->toContain('Hello');
    expect($testimonial->testimonial)->toContain('world');
});

it('strips script tags from each TestimonialValue value when persisted', function (): void {
    $space = SpaceModel::factory()->create();
    $action = new SubmitTestimonialAction;

    $testimonial = $action->create($space, [
        'name' => 'Priya',
        'email' => 'priya@example.com',
        'testimonial' => 'Great product, very helpful.',
        'consent_given' => true,
        'values' => [
            ['field_key' => 'company_name', 'value' => '<script>bad()</script>Acme'],
            ['field_key' => 'social_url', 'value' => '<b>https://example.com</b>'],
        ],
    ]);

    $values = $testimonial->values()->get();
    expect($values)->toHaveCount(2);

    $company = $values->firstWhere('space_field_id', $space->fields()->where('field_key', 'company_name')->value('id'));
    expect($company?->value)->not->toContain('<script>');
    expect($company?->value)->toContain('Acme');

    $social = $values->firstWhere('space_field_id', $space->fields()->where('field_key', 'social_url')->value('id'));
    expect($social?->value)->not->toContain('<b>');
    expect($social?->value)->toContain('https://example.com');
});

it('does not store the email field escaped (it is not rendered)', function (): void {
    // email is a structural column on testimonials; we never render it
    // and §13 keeps it server-side only. Sanitizing it would corrupt
    // lookups by exact email match.
    $space = SpaceModel::factory()->create();
    $action = new SubmitTestimonialAction;

    $testimonial = $action->create($space, [
        'name' => 'Priya',
        'email' => 'priya@example.com',
        'testimonial' => 'Great product.',
        'consent_given' => true,
    ]);

    expect($testimonial->email)->toBe('priya@example.com');
});
