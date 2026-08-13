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

    /**
     * Whether this visitor may play this drawing, and therefore see its image:
     * published drawings are everyone's, unpublished ones only their owner's.
     *
     * Callers turn a false into a 404 rather than a 403, so an id that belongs
     * to someone else cannot be told apart from one that does not exist.
     */
    public function isPlayableBy(?int $userId): bool
    {
        // The null check is not redundant. A drawing whose author deleted their
        // account has a null user_id, and a signed-out visitor's id is null
        // too — without this they would match, and an unpublished orphan would
        // be readable by anyone who was not logged in.
        return $this->published || ($userId !== null && $this->user_id === $userId);
    }

    /**
     * Whether this drawing carries everything needed to start playing at once.
     * Drawings saved before the settings columns existed have none of them,
     * and replaying those still goes through colour picking.
     *
     * The hazard colour is not part of the test: it is optional, so a level
     * saved without one is complete, and requiring it here would send those
     * back through colour picking on every replay.
     */
    public function hasGameSettings(): bool
    {
        return $this->platform_color !== null
            && $this->goal_color !== null
            && $this->player_color !== null;
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

    /**
     * @return HasMany<DrawingVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(DrawingVote::class);
    }
}
