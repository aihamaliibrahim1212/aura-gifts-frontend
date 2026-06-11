<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminUser extends Model
{
    protected $table = 'admin_users';
    protected $fillable = ['username', 'email', 'password_hash', 'role', 'is_active'];
    protected $hidden = ['password_hash'];

    public function toApiArray(): array
    {
        return [
            'id'         => $this->id,
            'username'   => $this->username,
            'email'      => $this->email,
            'role'       => $this->role,
            'is_active'  => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
