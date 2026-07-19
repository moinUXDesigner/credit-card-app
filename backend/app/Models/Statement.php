<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Statement extends Model
{
    use HasFactory;

    protected $attributes = [
        'analysis_status' => 'pending',
    ];

    protected $fillable = [
        'file_path',
        'original_filename',
        'billing_month',
        'billing_year',
        'analysis_status',
        'analysis_message',
        'statement_date',
        'due_date',
        'total_due',
        'minimum_due',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'due_date' => 'date',
            'total_due' => 'decimal:2',
            'minimum_due' => 'decimal:2',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
