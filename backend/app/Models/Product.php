<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name', 'description', 'price_mvr', 'stock', 'category',
        'sort_order', 'featured', 'badge', 'image_url',
        'cloudinary_public_id', 'is_active',
    ];

    public function toApiArray(): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'description'          => $this->description,
            'price_mvr'            => (float) $this->price_mvr,
            'price'                => 'MVR ' . number_format((float) $this->price_mvr, 2),
            'stock'                => (int) $this->stock,
            'sort_order'           => (int) $this->sort_order,
            'featured'             => (bool) $this->featured,
            'category'             => $this->category,
            'badge'                => $this->badge,
            'image_url'            => $this->image_url,
            'cloudinary_public_id' => $this->cloudinary_public_id,
            'is_active'            => (bool) $this->is_active,
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
