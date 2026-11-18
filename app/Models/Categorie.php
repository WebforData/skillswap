<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categorie extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée.
     */
    protected $table = 'categories';

    /**
     * Attributs assignables en masse.
     */
    protected $fillable = [
        'nom',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Offres d'échange rattachées à cette catégorie (1-to-Many).
     */
    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class, 'categorie_id');
    }
}

