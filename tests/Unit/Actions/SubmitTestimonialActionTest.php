<?php

declare(strict_types=1);

use App\Actions\SubmitTestimonialAction;

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
