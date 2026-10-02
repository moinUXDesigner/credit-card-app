<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject
{
    protected $attributes = ['role' => 'user'];
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function accessibleCards(): \Illuminate\Database\Eloquent\Builder
    {
        return Card::query()->where(function ($query) {
            $query->where('user_id', $this->id)->orWhereExists(function ($q) {
                $q->selectRaw('1')->from('card_memberships')->whereColumn('card_memberships.card_id', 'cards.id')->where('card_memberships.user_id', $this->id)->whereNotNull('accepted_at');
            });
        });
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
