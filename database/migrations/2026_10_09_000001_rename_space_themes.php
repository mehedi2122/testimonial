<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRD §10 names the themes Minimal, Modern and Clean. Map the earlier
 * values onto the closest new look.
 */
return new class extends Migration
{
    private const MAP = [
        'minimal_light' => 'minimal',
        'minimal_dark' => 'modern',
        'soft_color' => 'clean',
    ];

    public function up(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('spaces')->where('theme', $old)->update(['theme' => $new]);
        }

        Schema::table('spaces', function (Blueprint $table) {
            $table->string('theme', 32)->default('minimal')->change();
        });
    }

    public function down(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('spaces')->where('theme', $new)->update(['theme' => $old]);
        }

        Schema::table('spaces', function (Blueprint $table) {
            $table->string('theme', 32)->default('minimal_light')->change();
        });
    }
};
