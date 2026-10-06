<?php

namespace App\Models;

use Database\Factories\TestimonialValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Answers to space_fields. NEVER contains an email address — that is the
 * structural guarantee behind §31 (the API Resource whitelist is belt-and-braces).
 *
 * @property int $id
 * @property int $testimonial_id
 * @property int $space_field_id
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TestimonialValue extends Model
{
    /** @use HasFactory<TestimonialValueFactory> */
    use HasFactory;

    protected $fillable = [
        'testimonial_id',
        'space_field_id',
        'value',
    ];

    /**
     * @return BelongsTo<Testimonial, $this>
     */
    public function testimonial(): BelongsTo
    {
        return $this->belongsTo(Testimonial::class);
    }

    /**
     * @return BelongsTo<SpaceField, $this>
     */
    public function spaceField(): BelongsTo
    {
        return $this->belongsTo(SpaceField::class);
    }
}
