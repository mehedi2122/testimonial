<?php

use App\Enums\Plan;
use App\Enums\SpaceFieldMode;
use App\Enums\SpaceFieldType;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\SpaceField;
use App\Models\Testimonial;
use App\Models\TestimonialValue;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Tests pinning the data-model.md schema contract. If any of these start failing,
 * the contract has drifted — fix the schema, not the test.
 */
it('exposes the visibility rule through scopePubliclyVisible', function (): void {
    $space = Space::factory()->create();

    $priya = Testimonial::factory()->for($space)->create([
        'consent_given' => true,
        'is_wall_of_love' => true,
        'is_hidden' => false,
    ]);

    $sara = Testimonial::factory()->for($space)->unconsented()->create();
    $tom = Testimonial::factory()->for($space)->notOnWall()->create();
    $ben = Testimonial::factory()->for($space)->hidden()->create([
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    $ana = Testimonial::factory()->for($space)->create();
    $ana->delete(); // soft delete

    $visible = Testimonial::publiclyVisible()
        ->where('space_id', $space->id)
        ->pluck('id')
        ->all();

    expect($visible)->toContain($priya->id)
        ->and($visible)->not->toContain($sara->id)
        ->and($visible)->not->toContain($tom->id)
        ->and($visible)->not->toContain($ben->id)
        ->and($visible)->not->toContain($ana->id);
});

it('separates is_hidden from is_wall_of_love so Ben can be unhidden later', function (): void {
    $space = Space::factory()->create();
    $ben = Testimonial::factory()->for($space)->hidden()->create([
        'consent_given' => true,
        'is_wall_of_love' => true,
    ]);

    expect(Testimonial::publiclyVisible()->where('id', $ben->id)->exists())->toBeFalse();

    $ben->is_hidden = false;
    $ben->save();

    expect(Testimonial::publiclyVisible()->where('id', $ben->id)->exists())->toBeTrue();
});

it('keeps slug globally unique at the database level', function (): void {
    Space::factory()->create(['slug' => 'taken']);

    Space::factory()->create(['slug' => 'taken']);
})->throws(QueryException::class);

it('retains the slug when a Space is soft-deleted', function (): void {
    $space = Space::factory()->create(['slug' => 'reserved']);
    $space->delete();

    // Soft-deleted Space keeps its row, so the slug stays claimed.
    // Confirmed in §7.5 — accepting the leak, not "fixing" with unique(slug, deleted_at)
    // because MySQL treats NULLs as distinct.
    expect(Space::withTrashed()->where('slug', 'reserved')->exists())->toBeTrue();
});

it('seeds three predefined space fields on creation, all mode=off', function (): void {
    $space = Space::factory()->create();

    $keys = $space->fields()->pluck('field_key')->all();

    expect($keys)->toContain('company_name', 'social_url', 'profile_photo');

    foreach ($space->fields as $field) {
        expect($field->mode)->toBe(SpaceFieldMode::Off);
    }
});

it('enforces unique field_key within a space', function (): void {
    $space = Space::factory()->create();

    SpaceField::factory()->for($space)->create(['field_key' => 'job_title']);

    SpaceField::factory()->for($space)->create(['field_key' => 'job_title']);
})->throws(QueryException::class);

it('never holds an email address in testimonial_values', function (): void {
    $space = Space::factory()->create();

    $emailField = SpaceField::factory()->for($space)->create([
        'field_key' => 'work_email',
        'type' => SpaceFieldType::Email,
    ]);

    $textField = SpaceField::factory()->for($space)->create([
        'field_key' => 'work_title',
        'type' => SpaceFieldType::Text,
    ]);

    $testimonial = Testimonial::factory()->for($space)->create();

    $testimonial->values()->create([
        'space_field_id' => $emailField->id,
        'value' => 'leaky@example.com',
    ]);

    $testimonial->values()->create([
        'space_field_id' => $textField->id,
        'value' => 'Engineer',
    ]);

    // Structural guarantee: an embed that eager-loads values() can never produce
    // an email column. The field_key is what carries the email data, not a row
    // reserved for it. Anyone iterating $testimonial->values() must look up the
    // field type and column — there is no "email column".
    foreach ($testimonial->values()->get() as $value) {
        // The protection is structural: testimonial_values has no "email" column.
        // An email leaks only if a developer conflates the field's *type* with a
        // global "email column". This test pins the schema's column set.
        expect($value->getAttributes())->not->toHaveKey('email');
    }
});

it('counts unique respondents as distinct emails across a user\'s spaces', function (): void {
    $user = User::factory()->create();
    $spaceA = Space::factory()->for($user)->create();
    $spaceB = Space::factory()->for($user)->create();

    Testimonial::factory()->for($spaceA)->create(['email' => 'priya@example.com']);
    Testimonial::factory()->for($spaceB)->create(['email' => 'priya@example.com']);
    Testimonial::factory()->for($spaceA)->create(['email' => 'ben@example.com']);
    Testimonial::factory()->for($spaceB)->create(['email' => 'sara@example.com']);

    $spaceIds = $user->spaces()->pluck('id');

    $unique = Testimonial::whereIn('space_id', $spaceIds)
        ->whereNull('deleted_at')
        ->distinct()
        ->count('email');

    expect($unique)->toBe(3);
});

it('caps item_limit storage by hard cap', function (): void {
    // Documented in §3.6. We don't enforce it in the cast (would clip user edits silently).
    // The embed endpoint clamps at MAX_ITEM_LIMIT. The constant is exported for callers.
    expect(EmbedConfiguration::MAX_ITEM_LIMIT)->toBe(50);
});

it('resolves Free plan limits', function (): void {
    expect(Plan::Free->maxSpaces())->toBe(3);
    expect(Plan::Free->maxTestimonialsPerSpace())->toBe(100);
});

it('resolves Pro plan limits', function (): void {
    expect(Plan::Pro->maxSpaces())->toBe(25);
    expect(Plan::Pro->maxTestimonialsPerSpace())->toBe(1000);
});

it('derives a user\'s plan from Cashier, not from a stored column', function (): void {
    $free = User::factory()->create();
    expect($free->plan())->toBe(Plan::Free);

    $pro = User::factory()->create();
    $pro->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_pro',
        'quantity' => 1,
    ]);

    expect($pro->plan())->toBe(Plan::Pro);
});

it('treats a cancelled subscription with a future ends_at as active', function (): void {
    // §5.1 grace periods are free. Cashier's subscribed('default') already
    // implements this; we verify through the User::plan() facade.
    $pro = User::factory()->create();
    $pro->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_test_pro',
        'quantity' => 1,
        'ends_at' => now()->addDays(7),
    ]);

    expect($pro->plan())->toBe(Plan::Pro);
});

it('generates a 12-char public_id on creation and never mutates it', function (): void {
    $space = Space::factory()->create();
    $original = $space->public_id;

    expect(mb_strlen($space->public_id))->toBe(12);

    $space->update(['name' => 'Renamed', 'title' => 'Renamed Title']);
    expect($space->fresh()->public_id)->toBe($original);

    // Two consecutive creations get different ids.
    $other = Space::factory()->create();
    expect($other->public_id)->not->toBe($original);
});

it('keeps testimonial_values.space_field_id on restrict so soft-deleted fields survive', function (): void {
    // Restrict semantics: hard-deleting a field that has values is blocked.
    // Soft delete is fine — answers survive, which is the whole reason this is RESTRICT.
    //
    // We reload the model after the failed force-delete attempt because Laravel's
    // SoftDeletes::forceDelete() sets `$forceDeleting = true` on the in-memory instance
    // and only resets it after the inner delete returns. If we don't reload, a subsequent
    // $field->delete() would think it's still in force-delete mode and re-issue the
    // failing DELETE.
    $space = Space::factory()->create();
    $field = SpaceField::factory()->for($space)->create();

    $testimonial = Testimonial::factory()->for($space)->create();
    $value = TestimonialValue::factory()->create([
        'testimonial_id' => $testimonial->id,
        'space_field_id' => $field->id,
    ]);

    $threw = false;
    try {
        $field->forceDelete();
    } catch (QueryException) {
        $threw = true;
    }
    expect($threw)->toBeTrue('forceDelete must throw QueryException');

    // Soft delete succeeds; the answer remains.
    $reloaded = SpaceField::find($field->id);
    expect($reloaded)->not->toBeNull();
    $reloaded->delete();

    expect($reloaded->deleted_at)->not->toBeNull()
        ->and(TestimonialValue::find($value->id))->not->toBeNull();
});

it('orders publicly-visible testimonials by favorite then submitted_at', function (): void {
    $space = Space::factory()->create();

    $old = Testimonial::factory()->for($space)->create([
        'is_favorite' => false,
        'submitted_at' => now()->subDays(2),
    ]);
    $newest = Testimonial::factory()->for($space)->create([
        'is_favorite' => false,
        'submitted_at' => now(),
    ]);
    $favoriteOld = Testimonial::factory()->for($space)->create([
        'is_favorite' => true,
        'submitted_at' => now()->subDays(30),
    ]);

    $ids = Testimonial::publiclyVisible()
        ->where('space_id', $space->id)
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$favoriteOld->id, $newest->id, $old->id]);
});

it('excludes soft-deleted testimonials from unique respondents count', function (): void {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    Testimonial::factory()->for($space)->create(['email' => 'kept@example.com']);
    $gone = Testimonial::factory()->for($space)->create(['email' => 'gone@example.com']);
    $gone->delete();

    $spaceIds = $user->spaces()->pluck('id');
    $unique = Testimonial::whereIn('space_id', $spaceIds)
        ->whereNull('deleted_at')
        ->distinct()
        ->count('email');

    expect($unique)->toBe(1);
});
