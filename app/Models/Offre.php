<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offre extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée.
     */
    protected $table = 'offres';

    /**
     * Valeurs par défaut des attributs du modèle.
     */
    protected $attributes = [
        'statut' => 'brouillon',
        'niveau' => 'debutant',
        'duree' => 1,
        'mode' => 'presentiel',
    ];

    /**
     * Attributs assignables en masse.
     * SÉCURITÉ : 'etudiant_id' (propriétaire) et 'statut' (machine à états) sont protégés
     * contre le mass-assignment et doivent être définis par les services métier.
     */
    protected $fillable = [
        'categorie_id',
        'titre',
        'competence',
        'description',
        'niveau',
        'duree',
        'mode',
    ];

    /**
     * Typage des attributs.
     */
    protected function casts(): array
    {
        return [
            'etudiant_id' => 'integer',
            'categorie_id' => 'integer',
            'duree' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Étudiant intervenant ayant créé l'offre (Inverse 1-to-Many).
     */
    public function intervenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'etudiant_id');
    }

    /**
     * Catégorie thématique de l'offre (Inverse 1-to-Many).
     */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    /**
     * Créneaux horaires de disponibilité associés à cette offre (1-to-Many).
     */
    public function creneaux(): HasMany
    {
        return $this->hasMany(Creneau::class, 'offre_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES LOCAUX
    |--------------------------------------------------------------------------
    */

    /**
     * Filtre les offres publiées disponibles aux recherches (RG04).
     */
    public function scopePubliees(Builder $query): Builder
    {
        return $query->where('statut', 'publiee');
    }

    /**
     * Filtre les offres actives dont l'intervenant possède un compte actif (RG04, RG18).
     */
    public function scopeActives(Builder $query): Builder
    {
        return $query->where('statut', 'publiee')
            ->whereHas('intervenant', function ($q) {
                $q->where('statut', 'actif');
            });
    }
}

