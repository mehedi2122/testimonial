<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PRD §8 Address field for Spaces created before it was predefined.
 * Added `off` so live forms don't suddenly demand an address; the owner
 * turns it on in Settings. New Spaces get it on + required.
 */
return new class extends Migration
{
    public function up(): void
    {
        $spaceIds = DB::table('spaces')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('space_fields')
                ->whereColumn('space_fields.space_id', 'spaces.id')
                ->where('space_fields.field_key', 'address'))
            ->pluck('id');

        $now = now();

        foreach ($spaceIds as $spaceId) {
            DB::table('space_fields')->insert([
                'space_id' => $spaceId,
                'field_key' => 'address',
                'label' => 'Address',
                'type' => 'text',
                'mode' => 'off',
                'sort_order' => 5,
                'show_in_embed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Only remove the rows nobody has answered.
        DB::table('space_fields')
            ->where('field_key', 'address')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('testimonial_values')
                ->whereColumn('testimonial_values.space_field_id', 'space_fields.id'))
            ->delete();
    }
};
