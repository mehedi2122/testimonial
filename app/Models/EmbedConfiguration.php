<?php

namespace App\Models;

use App\Enums\EmbedLayout;
use Database\Factories\EmbedConfigurationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Exactly 0 or 1 per Space (unique on space_id). With no row, code defaults apply,
 * so the Embed page renders before it is ever saved.
 *
 * Where visibility toggles live (decision log #10):
 *   - rating → embed_configurations.show_rating
 *   - everything else → space_fields.show_in_embed
 *
 * @property int $id
 * @property int $space_id
 * @property EmbedLayout $layout
 * @property bool $dark_mode
 * @property bool $animation_enabled
 * @property string|null $background_color
 * @property int $item_limit
 * @property bool $show_rating
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EmbedConfiguration extends Model
{
    /** @use HasFactory<EmbedConfigurationFactory> */
    use HasFactory;

    public const MAX_ITEM_LIMIT = 50;

    protected $fillable = [
        'space_id',
        'layout',
        'dark_mode',
        'animation_enabled',
        'background_color',
        'item_limit',
        'show_rating',
    ];

    protected $casts = [
        'layout' => EmbedLayout::class,
        'dark_mode' => 'boolean',
        'animation_enabled' => 'boolean',
        'item_limit' => 'integer',
        'show_rating' => 'boolean',
    ];

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
