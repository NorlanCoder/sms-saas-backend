<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Company extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'email',
        'pays',
        'telephone',
        'solde',
        'statut',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'solde' => 'decimal:2',
            'statut' => 'string',
        ];
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany('App\\Models\\ApiKey');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany('App\\Models\\Transaction');
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany('App\\Models\\SmsLog');
    }

    public function senderIds(): HasMany
    {
        return $this->hasMany('App\\Models\\SenderID');
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany('App\\Models\\Country', 'company_countries')
            ->withTimestamps();
    }
}
