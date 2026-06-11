<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteContent extends Model
{
    protected $table = 'site_content';
    protected $fillable = ['key', 'value', 'content_type', 'updated_by'];

    public function toApiArray(): array
    {
        return [
            'id'           => $this->id,
            'key'          => $this->key,
            'value'        => $this->value,
            'content_type' => $this->content_type,
            'updated_at'   => $this->updated_at?->toISOString(),
            'updated_by'   => $this->updated_by,
        ];
    }
}
