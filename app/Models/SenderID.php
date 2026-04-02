<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SenderID extends Model
{
    use HasFactory;

    protected $table = 'sender_ids';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'nom',
        'statut',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'statut' => 'string',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeValide(Builder $query): Builder
    {
        return $query->where('statut', 'valide');
    }

    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->where('statut', 'en_attente');
    }
}
