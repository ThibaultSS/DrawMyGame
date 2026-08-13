<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'saved_drawing_id', 'value'])]
class DrawingVote extends Model
{
    /**
     * The only two values a vote may hold. Named, because otherwise a bare -1
     * turns up in the request rules, the controller, the gallery query and the
     * game page's props with nothing to say what it means.
     */
    public const LIKE = 1;

    public const DISLIKE = -1;

    /**
     * Cast because the page compares this against 1 and -1 in JavaScript, where
     * a string "1" from the driver would quietly never match.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }
}
