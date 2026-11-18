<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementCredit extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée.
     */
    protected $table = 'mouvements_credits';

    /**
     * SÉCURITÉ FINANCIÈRE ABSOLUE :
     * Aucun mouvement financier ne peut être créé ou modifié par mass-assignment.
     * Tout mouvement de crédits doit être généré exclusivement via la couche de service
     * (CreditService) avec instanciation explicite ou forceFill().
     */
    protected $guarded = ['*'];

    protected $fillable = [];

    /**
     * Typage des attributs.
     */
    protected function casts(): array
    {
        return [
            'portefeuille_id' => 'integer',
            'reservation_id' => 'integer',
            'quantite' => 'integer',
            'date_mouvement' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Portefeuille impacté par le mouvement de crédits (Inverse 1-to-Many).
     */
    public function portefeuille(): BelongsTo
    {
        return $this->belongsTo(Portefeuille::class, 'portefeuille_id');
    }

    /**
     * Séance / réservation liée à cette transaction financière (Inverse 1-to-Many, RG16).
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES LOCAUX
    |--------------------------------------------------------------------------
    */

    public function scopeBienvenue(Builder $query): Builder
    {
        return $query->where('type', 'bienvenue');
    }

    public function scopeBlocages(Builder $query): Builder
    {
        return $query->where('type', 'blocage');
    }

    public function scopeTransferts(Builder $query): Builder
    {
        return $query->where('type', 'transfert_gain');
    }
}

