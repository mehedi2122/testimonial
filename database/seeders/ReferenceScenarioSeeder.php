<?php

namespace Database\Seeders;

use App\Enums\Plan;
use App\Enums\SpaceFieldMode;
use App\Enums\SpaceFieldType;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\SpaceField;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the reference scenario from data-model.md §1.
 *
 * This is the fixture used by queries, UI states, and tests.
 *
 *   - Maya Sharma (Free)         → 2 Spaces (6 + 3 = 9 testimonials)
 *   - Dev Okafor  (Pro via sub)  → 2 Spaces (140 + 12 = 152 testimonials)
 *   - Priya Raman submits to BOTH of Dev's Spaces → unique respondents is distinct emails
 *   - Ben Fischer: consent=1, wall=1, hidden=1 → not public (publish intact, hide override)
 */
class ReferenceScenarioSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $maya = User::factory()->create([
                'name' => 'Maya Sharma',
                'email' => 'maya@brightcopy.co',
            ]);

            $dev = User::factory()->create([
                'name' => 'Dev Okafor',
                'email' => 'dev@shiplog.io',
            ]);

            // Dev is on Pro — subscribed('default') requires a Cashier subscription row.
            // We create one directly; in real flows it comes from a Stripe webhook.
            $this->subscribeDev($dev);

            $brightcopy = $this->createSpace(
                user: $maya,
                name: 'Brightcopy Client Wins',
                slug: 'brightcopy-wins',
                title: 'Brightcopy Client Wins',
                subtitle: 'Words from teams we have shipped with.',
                ask: 'Tell us about a project where we made a measurable difference.',
                testimonialCount: 6,
            );

            $course = $this->createSpace(
                user: $maya,
                name: 'Course Launch Feedback',
                slug: 'course-launch',
                title: 'Course Launch Feedback',
                subtitle: null,
                ask: 'How was your experience of the launch cohort?',
                testimonialCount: 3,
            );

            $shiplog = $this->createSpace(
                user: $dev,
                name: 'Shiplog Product Reviews',
                slug: 'shiplog-reviews',
                title: 'Shiplog Product Reviews',
                subtitle: 'Why teams keep their dashboards here.',
                ask: 'What changed for your team since you started using Shiplog?',
                testimonialCount: 140,
                includePriya: true,
            );

            $beta = $this->createSpace(
                user: $dev,
                name: 'Shiplog Beta Testers',
                slug: 'beta-testers',
                title: 'Shiplog Beta Testers',
                subtitle: 'Voices from the beta cohort.',
                ask: 'What worked, what did not, what is still rough?',
                testimonialCount: 12,
                includePriya: true,
            );

            // Ben Fischer is on Shiplog Product Reviews and hidden.
            // Per §3.4: hide is an override layered on a publish decision, not a replacement.
            Testimonial::factory()->for($shiplog)->create([
                'name' => 'Ben Fischer',
                'email' => 'ben@example.com',
                'testimonial' => 'A wall-of-love row that the owner wants suppressed for now.',
                'rating' => 5,
                'consent_given' => true,
                'is_wall_of_love' => true,
                'is_hidden' => true,
                'submitted_at' => Carbon::now()->subDays(3),
            ]);

            // Sara Nkemi did not consent — visibility rule rejects her regardless of other flags.
            Testimonial::factory()->for($shiplog)->unconsented()->create([
                'name' => 'Sara Nkemi',
                'email' => 'sara@example.com',
                'testimonial' => 'I love it but did not tick the box.',
                'rating' => 5,
                'is_wall_of_love' => true,
                'submitted_at' => Carbon::now()->subDays(4),
            ]);

            // Tom Alvarez has not been promoted to the wall yet.
            Testimonial::factory()->for($shiplog)->notOnWall()->create([
                'name' => 'Tom Alvarez',
                'email' => 'tom@example.com',
                'testimonial' => 'Solid product, will recommend when ready.',
                'rating' => 5,
                'consent_given' => true,
                'submitted_at' => Carbon::now()->subDays(2),
            ]);

            // Ana Ruiz is soft-deleted.
            Testimonial::factory()->for($shiplog)->create([
                'name' => 'Ana Ruiz',
                'email' => 'ana@example.com',
                'testimonial' => 'Removed by the respondent; soft-deleted per §3.4.',
                'rating' => 4,
                'consent_given' => true,
                'is_wall_of_love' => true,
                'submitted_at' => Carbon::now()->subDays(30),
            ])->delete();
        });
    }

    private function subscribeDev(User $dev): void
    {
        // Minimal subscription row so $dev->subscribed('default') returns true and
        // $dev->plan() resolves to Pro. Mirrors what Cashier's webhook handlers do.
        $dev->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => 'price_test_pro',
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);
    }

    private function createSpace(
        User $user,
        string $name,
        string $slug,
        string $title,
        ?string $subtitle,
        string $ask,
        int $testimonialCount,
        bool $includePriya = false,
    ): Space {
        $space = Space::factory()->for($user)->create([
            'name' => $name,
            'slug' => $slug,
            'public_id' => substr(md5($slug), 0, 12),
            'title' => $title,
            'subtitle' => $subtitle,
            'ask' => $ask,
        ]);

        // Seed the three predefined fields at mode = 'off'.
        foreach (SpaceField::PREDEFINED_FIELDS as $row) {
            SpaceField::factory()->for($space)->create([
                'field_key' => $row['field_key'],
                'label' => $row['label'],
                'type' => $row['type'],
                'sort_order' => $row['sort_order'],
                'mode' => SpaceFieldMode::Off,
            ]);
        }

        // A custom field, sort_order > 30, optional.
        SpaceField::factory()->for($space)->create([
            'field_key' => 'job_title',
            'label' => 'Job title',
            'type' => SpaceFieldType::Text,
            'mode' => SpaceFieldMode::Optional,
            'sort_order' => 40,
        ]);

        // Default embed config so the Embed page renders before any user editing.
        EmbedConfiguration::factory()->for($space)->create();

        // Generate the bulk testimonials with one Priya Raman thrown in if requested.
        $remaining = $testimonialCount;
        if ($includePriya) {
            $this->createPriyaSubmission($space);
            $remaining--;
        }

        Testimonial::factory()
            ->count($remaining)
            ->for($space)
            ->create([
                'submitted_at' => Carbon::now()->subDays(fake()->numberBetween(1, 90)),
            ]);

        return $space;
    }

    private function createPriyaSubmission(Space $space): void
    {
        $testimonial = Testimonial::factory()->for($space)->create([
            'name' => 'Priya Raman',
            'email' => 'priya@nimbus.dev',
            'testimonial' => 'Shiplog kept our release trains on the rails when things got noisy.',
            'rating' => 5,
            'consent_given' => true,
            'is_wall_of_love' => true,
            'is_favorite' => true,
            'submitted_at' => Carbon::now()->subDays(7),
        ]);

        $fields = $space->fields()->get()->keyBy('field_key');

        /** @var array<string, SpaceField> $fields */
        $testimonial->values()->createMany([
            ['space_field_id' => $fields['company_name']->id, 'value' => 'Nimbus Dev'],
            ['space_field_id' => $fields['social_url']->id, 'value' => 'https://nimbus.dev/priya'],
            ['space_field_id' => $fields['job_title']->id, 'value' => 'VP Engineering'],
        ]);
    }
}
