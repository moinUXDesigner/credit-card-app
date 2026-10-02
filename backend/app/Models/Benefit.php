<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Benefit extends Model
{
    use HasFactory;

    protected $attributes = [
        'revision' => 1,
        'used_count' => 0,
    ];

    protected $fillable = [
        'type',
        'title',
        'frequency',
        'total_allowed',
        'used_count',
        'expiry_date',
        'estimated_value',
        'cycle_start_date',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'cycle_start_date' => 'date',
            'estimated_value' => 'decimal:2',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(BenefitUsageLog::class);
    }
}
