<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée.
     */
    protected $table = 'evaluations';

    /**
     * Attributs assignables en masse.
     * SÉCURITÉ : 'auteur_id' et 'cible_id' sont strictement protégés contre le mass-assignment.
     * Ils doivent être déterminés automatiquement par le service métier en fonction de la réservation.
     */
    protected $fillable = [
        'reservation_id',
        'note',
        'commentaire',
    ];

    /**
     * Typage des attributs.
     */
    protected function casts(): array
    {
        return [
            'reservation_id' => 'integer',
            'auteur_id' => 'integer',
            'cible_id' => 'integer',
            'note' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Réservation réalisée objet de cette évaluation (Inverse 1-to-Many).
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    /**
     * Utilisateur ayant rédigé l'évaluation (Inverse 1-to-Many).
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    /**
     * Utilisateur évalué (Inverse 1-to-Many).
     */
    public function cible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cible_id');
    }
}
