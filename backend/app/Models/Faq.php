<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $table = 'faqs';
    protected $fillable = ['question', 'answer', 'sort_order', 'is_active'];

    public function toApiArray(): array
    {
        return [
            'id'         => $this->id,
            'question'   => $this->question,
            'answer'     => $this->answer,
            'sort_order' => (int) $this->sort_order,
            'is_active'  => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
