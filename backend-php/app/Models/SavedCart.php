<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedCart extends Model
{
    protected $table = 'saved_carts';

    protected $fillable = ['user_id', 'items'];

    protected $casts = ['items' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
