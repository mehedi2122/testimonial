<?php

namespace App\Models;

use App\Enums\SpaceFieldMode;
use App\Enums\SpaceTheme;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string $public_id
 * @property string $title
 * @property string|null $subtitle
 * @property string $ask
 * @property SpaceTheme $theme
 * @property bool $rating_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Collection<int, SpaceField> $fields
 * @property-read Collection<int, Testimonial> $testimonials
 * @property-read EmbedConfiguration|null $embedConfiguration
 */
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'public_id',
        'title',
        'subtitle',
        'ask',
        'theme',
        'rating_enabled',
    ];

    protected $casts = [
        'theme' => SpaceTheme::class,
        'rating_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Space $space): void {
            // public_id is non-sequential and immutable; generate once on insert.
            if (empty($space->public_id)) {
                $space->public_id = static::generatePublicId();
            }
        });

        static::created(function (Space $space): void {
            // Every new Space gets three predefined rows at mode = 'off'.
            // Custom fields append with sort_order above 30.
            foreach (SpaceField::PREDEFINED_FIELDS as $row) {
                $space->fields()->create([
                    'field_key' => $row['field_key'],
                    'label' => $row['label'],
                    'type' => $row['type'],
                    'sort_order' => $row['sort_order'],
                    'mode' => SpaceFieldMode::Off,
                ]);
            }
        });
    }

    /**
     * Two public identifiers, deliberately (decision log #2).
     *  - slug → /s/{slug}, user-editable, for respondents.
     *  - public_id → embed snippet, immutable, for external sites.
     */
    public static function generatePublicId(): string
    {
        // 12 chars from a 62-char alphabet gives ~10^21 keyspace.
        return Str::lower(Str::random(12));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(SpaceField::class);
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function embedConfiguration(): HasOne
    {
        return $this->hasOne(EmbedConfiguration::class);
    }
}
