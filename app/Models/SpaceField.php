<?php

namespace App\Models;

use App\Enums\SpaceFieldMode;
use App\Enums\SpaceFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Holds both predefined and user-defined respondent fields uniformly.
 *
 * Why name/email are NOT rows here: they are columns on testimonials,
 * always required, and the email guarantee is the data model
 * (testimonial_values never holds an email — see testimonial_values migration).
 *
 * @property int $id
 * @property int $space_id
 * @property string $field_key
 * @property string $label
 * @property SpaceFieldType $type
 * @property SpaceFieldMode $mode
 * @property int $sort_order
 * @property bool $show_in_embed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SpaceField extends Model
{
    /** @use HasFactory<\Database\Factories\SpaceFieldFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'space_id',
        'field_key',
        'label',
        'type',
        'mode',
        'sort_order',
        'show_in_embed',
    ];

    protected $casts = [
        'type' => SpaceFieldType::class,
        'mode' => SpaceFieldMode::class,
        'sort_order' => 'integer',
        'show_in_embed' => 'boolean',
    ];

    /** Predefined field_keys seeded into every new Space at mode = 'off'. */
    public const PREDEFINED_FIELDS = [
        ['field_key' => 'company_name', 'label' => 'Company name', 'type' => SpaceFieldType::Text, 'sort_order' => 10],
        ['field_key' => 'social_url', 'label' => 'Social profile URL', 'type' => SpaceFieldType::Url, 'sort_order' => 20],
        ['field_key' => 'profile_photo', 'label' => 'Profile photo', 'type' => SpaceFieldType::Image, 'sort_order' => 30],
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(TestimonialValue::class);
    }
}