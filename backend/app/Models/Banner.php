<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'eyebrow', 'title', 'subtitle', 'image_url',
        'cloudinary_public_id', 'sort_order', 'is_active',
    ];

    public function toApiArray(): array
    {
        return [
            'id'                   => $this->id,
            'eyebrow'              => $this->eyebrow,
            'title'                => $this->title,
            'subtitle'             => $this->subtitle,
            'image_url'            => $this->image_url,
            'cloudinary_public_id' => $this->cloudinary_public_id,
            'sort_order'           => (int) $this->sort_order,
            'is_active'            => (bool) $this->is_active,
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
