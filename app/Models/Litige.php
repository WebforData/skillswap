<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Litige extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée.
     */
    protected $table = 'litiges';

    /**
     * Valeurs par défaut des attributs du modèle.
     */
    protected $attributes = [
        'statut' => 'ouvert',
    ];

    /**
     * Attributs assignables en masse.
     * SÉCURITÉ : 'declarant_id', 'admin_id', 'statut', 'decision', 'motif_decision'
     * et 'date_resolution' sont protégés. Un étudiant ne peut fournir que la réservation
     * et la description lors de la déclaration initiale.
     */
    protected $fillable = [
        'reservation_id',
        'description',
    ];

    /**
     * Typage des attributs.
     */
    protected function casts(): array
    {
        return [
            'reservation_id' => 'integer',
            'declarant_id' => 'integer',
            'admin_id' => 'integer',
            'date_resolution' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Réservation concernée par ce litige (Inverse 1-to-1 unique, RG13, RG14).
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    /**
     * Utilisateur ayant ouvert le litige (Inverse 1-to-Many).
     */
    public function declarant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declarant_id');
    }

    /**
     * Administrateur ayant pris la décision arbitrale (Inverse 1-to-Many).
     */
    public function administrateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES LOCAUX
    |--------------------------------------------------------------------------
    */

    public function scopeOuverts(Builder $query): Builder
    {
        return $query->where('statut', 'ouvert');
    }

    public function scopeResolus(Builder $query): Builder
    {
        return $query->where('statut', 'resolu');
    }
}

