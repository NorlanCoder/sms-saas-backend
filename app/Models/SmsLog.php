<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'sender_id',
        'destinataire',
        'message',
        'statut',
        'cout',
        'batch_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'cout' => 'decimal:4',
        'statut' => 'string',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(SenderID::class, 'sender_id');
    }

    public function scopeEnvoye(Builder $query): Builder
    {
        return $query->where('statut', 'envoye');
    }

    public function scopeEchoue(Builder $query): Builder
    {
        return $query->where('statut', 'echoue');
    }
}
