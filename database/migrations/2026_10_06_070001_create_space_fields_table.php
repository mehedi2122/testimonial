<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('spaces')->cascadeOnDelete();
            $table->string('field_key', 40);
            $table->string('label', 80);
            $table->string('type', 16);
            $table->string('mode', 16)->default('off');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('show_in_embed')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['space_id', 'field_key']);
            $table->index(['space_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_fields');
    }
};