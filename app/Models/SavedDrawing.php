<?php

namespace App\Models;

use Database\Factories\SavedDrawingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'image_path',
    'published',
    'title',
    'description',
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

    public function isPlayableBy(?int $userId): bool
    {
        return $this->published || ($userId !== null && $this->user_id === $userId);
    }

    public function hasGameSettings(): bool
    {
        return $this->platform_color !== null
            && $this->goal_color !== null
            && $this->player_color !== null;
    }

    /**
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

    /**
     * @return HasMany<DrawingVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(DrawingVote::class);
    }

    /**
     * @return HasMany<LevelFavourite, $this>
     */
    public function favourites(): HasMany
    {
        return $this->hasMany(LevelFavourite::class);
    }

    /**
     * @return HasMany<LevelPlay, $this>
     */
    public function plays(): HasMany
    {
        return $this->hasMany(LevelPlay::class);
    }
}
