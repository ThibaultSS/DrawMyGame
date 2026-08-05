<?php

namespace App\Models;

use Database\Factories\SavedDrawingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'image_path',
    'published',
    'platform_color',
    'goal_color',
    'player_color',
    'hazard_color',
    'speed',
    'jump_height',
])]
class SavedDrawing extends Model
{
    /** @use HasFactory<SavedDrawingFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Whether this drawing carries everything needed to start playing at once.
     * Drawings saved before the settings columns existed have none of them,
     * and replaying those still goes through colour picking.
     */
    public function hasGameSettings(): bool
    {
        return $this->platform_color !== null
            && $this->goal_color !== null
            && $this->player_color !== null
            && $this->hazard_color !== null;
    }

    /**
     * Without this, published comes back from the database as 0 or 1, and every
     * caller has to remember to cast it.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
