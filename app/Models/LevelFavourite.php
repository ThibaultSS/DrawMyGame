<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'saved_drawing_id', 'speed', 'jump_height'])]
class LevelFavourite extends Model
{
    /**
     * @return BelongsTo<SavedDrawing, $this>
     */
    public function drawing(): BelongsTo
    {
        return $this->belongsTo(SavedDrawing::class, 'saved_drawing_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speed' => 'integer',
            'jump_height' => 'integer',
        ];
    }
}
