<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('spaces')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 180);
            $table->text('testimonial');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('consent_given')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_wall_of_love')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_public')->default(false);
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->softDeletes();

            // Embed reads ordered by (public, favorite, submitted_at desc) — no sort needed.
            $table->index(['space_id', 'is_public', 'is_favorite', 'submitted_at'], 'idx_public');
            // Live counts, inbox, dashboard.
            $table->index(['space_id', 'deleted_at', 'submitted_at'], 'idx_space_live');
            // Unique respondents aggregate.
            $table->index(['space_id', 'deleted_at', 'email'], 'idx_respondents');
        });

        // MySQL STORED generated column enforces the §15 visibility rule in the schema.
        // SQLite cannot create a STORED expression referencing deleted_at via Blueprint,
        // so we add the equivalent expression for MySQL only. The app-facing
        // scopePubliclyVisible() remains the source of truth everywhere; this is belt-and-braces.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE testimonials
                DROP COLUMN is_public
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE testimonials
                ADD COLUMN is_public TINYINT(1) GENERATED ALWAYS AS (
                    CASE WHEN consent_given = 1
                              AND is_wall_of_love = 1
                              AND is_hidden = 0
                              AND deleted_at IS NULL
                         THEN 1 ELSE 0 END
                ) STORED
            SQL);
            // Rebuild the index to include the new column.
            DB::statement('ALTER TABLE testimonials DROP INDEX idx_public');
            DB::statement('CREATE INDEX idx_public ON testimonials (space_id, is_public, is_favorite, submitted_at)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};