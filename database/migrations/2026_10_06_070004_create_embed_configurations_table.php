<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embed_configurations', function (Blueprint $table) {
            $table->id();
            // Exactly 0 or 1 per Space; unique enforces that.
            $table->foreignId('space_id')->unique()->constrained('spaces')->cascadeOnDelete();
            $table->string('layout', 16)->default('masonry');
            $table->boolean('dark_mode')->default(false);
            $table->boolean('animation_enabled')->default(true);
            $table->char('background_color', 7)->nullable();
            $table->unsignedSmallInteger('item_limit')->default(12);
            $table->boolean('show_rating')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embed_configurations');
    }
};
