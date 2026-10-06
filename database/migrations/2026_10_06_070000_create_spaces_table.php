<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 60)->unique();
            $table->char('public_id', 12)->unique();
            $table->string('title', 160);
            $table->string('subtitle', 255)->nullable();
            $table->text('ask');
            $table->string('theme', 32)->default('minimal_light');
            $table->boolean('rating_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
