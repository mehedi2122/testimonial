<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Free text used to be stored entity-encoded (htmlspecialchars on write)
 * and was then escaped again on render, so "Tom & Jerry" displayed as
 * "Tom &amp; Jerry". Text is now stored plain (tags stripped) and only
 * escaped on render; decode what was written the old way.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('testimonials')
            ->select(['id', 'name', 'testimonial'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $name = $this->decode($row->name);
                    $text = $this->decode($row->testimonial);

                    if ($name !== $row->name || $text !== $row->testimonial) {
                        DB::table('testimonials')->where('id', $row->id)->update([
                            'name' => $name,
                            'testimonial' => $text,
                        ]);
                    }
                }
            });

        DB::table('testimonial_values')
            ->select(['id', 'value'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $value = $this->decode($row->value);

                    if ($value !== $row->value) {
                        DB::table('testimonial_values')->where('id', $row->id)->update(['value' => $value]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Irreversible by design: re-encoding would reintroduce the bug.
    }

    private function decode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Decoded text may contain markup that used to be inert; strip it,
        // matching what SanitizesFreeText now does on write.
        return strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
};
