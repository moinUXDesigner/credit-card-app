<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BenefitUsageLog extends Model
{
    use HasFactory;

    protected $fillable = ['used_on', 'quantity'];

    protected function casts(): array
    {
        return [
            'used_on' => 'date',
        ];
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }
}
