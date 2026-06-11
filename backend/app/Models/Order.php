<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'customer_name', 'customer_email', 'customer_phone',
        'atoll', 'island', 'address', 'boat_name', 'boat_number',
        'items', 'total_mvr', 'status', 'notes',
    ];

    protected $casts = ['items' => 'array'];

    public function toApiArray(): array
    {
        return [
            'id'             => $this->id,
            'user_id'        => $this->user_id,
            'customer_name'  => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'atoll'          => $this->atoll,
            'island'         => $this->island,
            'address'        => $this->address,
            'boat_name'      => $this->boat_name,
            'boat_number'    => $this->boat_number,
            'items'          => $this->items,
            'total_mvr'      => $this->total_mvr !== null ? (float) $this->total_mvr : null,
            'status'         => $this->status,
            'notes'          => $this->notes,
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }
}
