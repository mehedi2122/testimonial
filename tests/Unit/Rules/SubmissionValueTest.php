<?php

declare(strict_types=1);

use App\Enums\SpaceFieldType;
use App\Rules\SubmissionValue;

/**
 * Helper: run the rule and return the captured failure messages.
 *
 * @return list<string>
 */
function runRule(SubmissionValue $rule, mixed $value): array
{
    $messages = [];
    $rule->validate('value', $value, function (string $m) use (&$messages): void {
        $messages[] = $m;
    });

    return $messages;
}

it('accepts text values within 500 chars', function (): void {
    $rule = new SubmissionValue(SpaceFieldType::Text);

    expect(runRule($rule, 'Acme Corp'))->toBe([]);
    expect(runRule($rule, str_repeat('a', 500)))->toBe([]);
});

it('rejects text values over 500 chars', function (): void {
    $rule = new SubmissionValue(SpaceFieldType::Text);

    expect(runRule($rule, str_repeat('a', 501)))->not->toBe([]);
});

it('rejects non-string text values', function (): void {
    $rule = new SubmissionValue(SpaceFieldType::Text);

    expect(runRule($rule, 123))->not->toBe([]);
    expect(runRule($rule, ['x']))->not->toBe([]);
});

it('accepts valid URLs', function (): void {
    $rule = new SubmissionValue(SpaceFieldType::Url);

    expect(runRule($rule, 'https://example.com'))->toBe([]);
    expect(runRule($rule, 'https://sub.example.co/path?q=1#h'))->toBe([]);
});

it('rejects invalid URLs', function (): void {
    $rule = new SubmissionValue(SpaceFieldType::Url);

    expect(runRule($rule, 'not a url'))->not->toBe([]);
    expect(runRule($rule, 'just-text'))->not->toBe([]);
});

it('rejects URLs over 2048 chars', function (): void {
    $rule = new SubmissionValue(SpaceFieldType::Url);

    expect(runRule($rule, 'https://example.com/'.str_repeat('a', 2030)))->not->toBe([]);
});

it('accepts numbers within -1e9 to 1e9', function (): void {
    $rule = new SubmissionValue(SpaceFieldType::Number);

    expect(runRule($rule, 0))->toBe([]);
    expect(runRule($rule, 42))->toBe([]);
    expect(runRule($rule, -999_999_999))->toBe([]);
    expect(runRule($rule, 999_999_999))->toBe([]);
    expect(runRule($rule, '123.45'))->toBe([]); // numeric strings are fine
});

it('rejects numbers outside the allowed range', function (): void {
    $rule = new SubmissionValue(SpaceFieldType::Number);

    expect(runRule($rule, 2_000_000_000))->not->toBe([]);
    expect(runRule($rule, -2_000_000_000))->not->toBe([]);
});

it('rejects non-numeric values', function (): void {
    $rule = new SubmissionValue(SpaceFieldType::Number);

    expect(runRule($rule, 'not-a-number'))->not->toBe([]);
    expect(runRule($rule, ['x']))->not->toBe([]);
});

it('rejects email-typed fields unconditionally', function (): void {
    $rule = new SubmissionValue(SpaceFieldType::Email);

    expect(runRule($rule, 'foo@example.com'))->not->toBe([]);
    expect(runRule($rule, ''))->not->toBe([]);
});

it('accepts image paths up to 255 chars', function (): void {
    $rule = new SubmissionValue(SpaceFieldType::Image);

    expect(runRule($rule, '/storage/photos/abc.jpg'))->toBe([]);
    expect(runRule($rule, str_repeat('a', 255)))->toBe([]);
});

it('rejects image paths over 255 chars', function (): void {
    $rule = new SubmissionValue(SpaceFieldType::Image);

    expect(runRule($rule, str_repeat('a', 256)))->not->toBe([]);
});

it('rejects non-string image values', function (): void {
    $rule = new SubmissionValue(SpaceFieldType::Image);

    expect(runRule($rule, 123))->not->toBe([]);
});
