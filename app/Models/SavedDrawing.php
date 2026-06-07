<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedDrawing extends Model
{
    protected $fillable = ['user_id', 'image_path'];
}
