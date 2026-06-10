<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class SavedDrawing extends Model
{
    
    use SoftDeletes;
    protected $fillable = ['user_id', 'image_path', 'published'];

    public function user()
{
    return $this->belongsTo(User::class);
}
}
