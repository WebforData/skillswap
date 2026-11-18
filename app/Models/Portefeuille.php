<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Portefeuille extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée.
     */
    protected $table = 'portefeuilles';

    /**
     * Valeurs par défaut des attributs du modèle.
     */
    protected $attributes = [
        'solde_disponible' => 0,
        'credits_bloques' => 0,
    ];

    /**
     * SÉCURITÉ FINANCIÈRE : 'solde_disponible' et 'credits_bloques' sont strictement non modifiables
     * par mass-assignment. Seul 'etudiant_id' peut être renseigné lors de la création initiale du compte.
     */
    protected $fillable = [
        'etudiant_id',
    ];

    protected $guarded = [
        'id',
        'solde_disponible',
        'credits_bloques',
        'created_at',
        'updated_at',
    ];

    /**
     * Typage des attributs.
     */
    protected function casts(): array
    {
        return [
            'etudiant_id' => 'integer',
            'solde_disponible' => 'integer',
            'credits_bloques' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Étudiant propriétaire du portefeuille (Inverse 1-to-1).
     */
    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'etudiant_id');
    }

    /**
     * Historique immuable de tous les mouvements financiers du portefeuille (1-to-Many).
     */
    public function mouvementsCredits(): HasMany
    {
        return $this->hasMany(MouvementCredit::class, 'portefeuille_id');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSEURS
    |--------------------------------------------------------------------------
    */

    /**
     * Calcule le solde total possédé (disponible + bloqué).
     */
    public function getSoldeTotalAttribute(): int
    {
        return $this->solde_disponible + $this->credits_bloques;
    }
}

