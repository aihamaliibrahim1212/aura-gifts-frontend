<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = ['reviewer_name', 'reviewer_location', 'rating', 'text', 'is_approved'];

    public function toApiArray(): array
    {
        return [
            'id'                => $this->id,
            'reviewer_name'     => $this->reviewer_name,
            'reviewer_location' => $this->reviewer_location,
            'rating'            => (int) $this->rating,
            'text'              => $this->text,
            'is_approved'       => (bool) $this->is_approved,
            'created_at'        => $this->created_at?->toISOString(),
        ];
    }
}
