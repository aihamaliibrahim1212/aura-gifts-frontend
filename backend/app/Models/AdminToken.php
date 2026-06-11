<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminToken extends Model
{
    protected $table = 'admin_tokens';
    protected $fillable = ['user_id', 'token', 'expires_at'];
    protected $casts = ['expires_at' => 'datetime'];
}
