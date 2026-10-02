<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlySpendEntry extends Model
{
    protected $attributes = ['revision' => 1];
    use HasFactory;

    protected $fillable = ['year', 'month', 'amount_spent', 'category', 'manual_amount'];

    protected function casts(): array
    {
        return [
            'amount_spent' => 'decimal:2',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
