<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonial_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('testimonial_id')->constrained('testimonials')->cascadeOnDelete();
            // restrict per §9.1 — answers survive when a field is soft-deleted.
            $table->foreignId('space_field_id')->constrained('space_fields')->restrictOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['testimonial_id', 'space_field_id']);
            $table->index('space_field_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonial_values');
    }
};