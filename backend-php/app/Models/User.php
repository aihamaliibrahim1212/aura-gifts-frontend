<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'name', 'email', 'password_hash', 'avatar_url',
        'provider', 'google_id', 'role',
        'email_verified', 'email_verify_token', 'is_active',
    ];

    protected $hidden = ['password_hash', 'email_verify_token'];

    protected $casts = [
        'email_verified' => 'boolean',
        'is_active'      => 'boolean',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function savedCart()
    {
        return $this->hasOne(SavedCart::class, 'user_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'email'          => $this->email,
            'avatar_url'     => $this->avatar_url,
            'provider'       => $this->provider,
            'role'           => $this->role,
            'email_verified' => (bool) $this->email_verified,
            'is_active'      => (bool) $this->is_active,
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
