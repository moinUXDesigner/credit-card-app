<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiRequest extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['result' => 'array', 'usage' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
