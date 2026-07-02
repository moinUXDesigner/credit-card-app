<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Card extends Model
{
    use HasFactory;

    protected $attributes = [
        'current_outstanding' => 0,
        'annual_fee_amount' => 0,
        'waiver_spend_required' => 0,
        'waiver_spend_completed' => 0,
        'reward_point_balance' => 0,
        'reward_point_value_estimate' => 0,
        'lounge_access' => false,
        'is_active' => true,
    ];

    protected $fillable = [
        'card_name',
        'bank_name',
        'last_four_digits',
        'network',
        'total_limit',
        'shared_limit_group',
        'current_outstanding',
        'statement_day',
        'due_day',
        'annual_fee_amount',
        'annual_fee_month',
        'waiver_spend_required',
        'waiver_spend_completed',
        'card_year_start_month',
        'reward_point_balance',
        'reward_point_value_estimate',
        'best_categories',
        'reward_rate_general',
        'cashback_cap_amount',
        'lounge_access',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_limit' => 'decimal:2',
            'current_outstanding' => 'decimal:2',
            'annual_fee_amount' => 'decimal:2',
            'waiver_spend_required' => 'decimal:2',
            'waiver_spend_completed' => 'decimal:2',
            'reward_point_balance' => 'decimal:2',
            'reward_point_value_estimate' => 'decimal:4',
            'reward_rate_general' => 'decimal:2',
            'cashback_cap_amount' => 'decimal:2',
            'best_categories' => 'array',
            'lounge_access' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(Benefit::class);
    }

    public function spendEntries(): HasMany
    {
        return $this->hasMany(MonthlySpendEntry::class);
    }

    /**
     * Cards (including this one) that pool their credit limit together —
     * i.e. share the same non-null `shared_limit_group` for this user.
     * Falls back to a single-card collection when this card has no group.
     */
    public function limitGroupCards(): Collection
    {
        if (! $this->shared_limit_group) {
            return new Collection([$this]);
        }

        return static::query()
            ->where('user_id', $this->user_id)
            ->where('shared_limit_group', $this->shared_limit_group)
            ->get();
    }
}
