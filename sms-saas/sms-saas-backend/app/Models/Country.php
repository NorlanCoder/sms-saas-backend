<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Country extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'code_pays',
        'code_indicatif',
        'tarif_sms',
        'statut',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tarif_sms' => 'decimal:4',
        'statut' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('statut', true);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_countries')
            ->withPivot('actif')
            ->withTimestamps();
    }
}
