<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyCountry extends Model
{
    use HasFactory;

    protected $table = 'company_countries';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'country_id',
        'actif',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'actif' => 'boolean',
    ];
}
