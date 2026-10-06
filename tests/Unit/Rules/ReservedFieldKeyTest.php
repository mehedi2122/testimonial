<?php

declare(strict_types=1);

use App\Rules\ReservedFieldKey;

it('rejects reserved field keys case-insensitively', function (string $key): void {
    $rule = new ReservedFieldKey;
    $failed = false;

    $rule->validate('field_key', $key, function (string $message) use (&$failed): void {
        $failed = true;
        expect($message)->toContain('reserved');
    });

    expect($failed)->toBeTrue();
})->with([
    ['name'],
    ['NAME'],
    ['Name'],
    ['email'],
    ['EMAIL'],
    ['testimonial'],
    ['rating'],
    ['consent_given'],
    ['is_wall_of_love'],
    ['is_hidden'],
    ['is_favorite'],
    ['is_public'],
    ['public_id'],
    ['slug'],
    ['id'],
    ['user_id'],
    ['space_id'],
    ['submitted_at'],
    ['created_at'],
    ['updated_at'],
    ['deleted_at'],
]);

it('accepts non-reserved field keys', function (string $key): void {
    $rule = new ReservedFieldKey;
    $failed = false;

    $rule->validate('field_key', $key, function (string $message) use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
})->with([
    ['company_name'],
    ['social_url'],
    ['profile_photo'],
    ['job_title'],
    ['team_size'],
    ['industry'],
]);

it('rejects non-string values', function (mixed $value): void {
    $rule = new ReservedFieldKey;
    $failed = false;

    $rule->validate('field_key', $value, function (string $message) use (&$failed): void {
        $failed = true;
        expect($message)->toContain('string');
    });

    expect($failed)->toBeTrue();
})->with([
    [123],
    [null],
    [true],
]);
