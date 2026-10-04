<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatementPreview extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['result' => 'array', 'expires_at' => 'datetime'];
    }
}
