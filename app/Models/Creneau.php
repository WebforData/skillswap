<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Creneau extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée.
     */
    protected $table = 'creneaux';

    /**
     * Attributs assignables en masse.
     */
    protected $fillable = [
        'offre_id',
        'date',
        'heure_debut',
        'heure_fin',
        'lieu_public',
        'lien_ou_lieu_prive',
    ];

    /**
     * Attributs masqués par défaut (RG19: lien et lieu privé strictement protégés).
     */
    protected $hidden = [
        'lien_ou_lieu_prive',
    ];

    /**
     * Typage des attributs.
     */
    protected function casts(): array
    {
        return [
            'offre_id' => 'integer',
            'date' => 'date',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Offre parente à laquelle appartient ce créneau (Inverse 1-to-Many).
     */
    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class, 'offre_id');
    }

    /**
     * Réservations formulées sur ce créneau (1-to-Many).
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'creneau_id');
    }

    /**
     * Réservation validée/attribuée sur ce créneau (1-to-1 filtrée, RG07).
     */
    public function reservationAcceptee(): HasOne
    {
        return $this->hasOne(Reservation::class, 'creneau_id')
            ->whereIn('statut', ['acceptee', 'realisee', 'en_litige']);
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIDENTIALITÉ & AUTORISATIONS (RG19)
    |--------------------------------------------------------------------------
    */

    /**
     * Vérifie si un utilisateur est autorisé à consulter le lien privé ou lieu exact.
     * Accessible uniquement :
     * 1. À l'intervenant de l'offre
     * 2. Au bénéficiaire d'une réservation confirmée (acceptee, realisee, en_litige)
     */
    public function peutAccederAuxDetailsPrives(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Intervenant propriétaire de l'offre
        if ((int) $this->offre?->etudiant_id === (int) $user->id) {
            return true;
        }

        // Bénéficiaire ayant une réservation active/validée
        return $this->reservations()
            ->where('beneficiaire_id', $user->id)
            ->whereIn('statut', ['acceptee', 'realisee', 'en_litige'])
            ->exists();
    }

    /**
     * Retourne les coordonnées privées si l'utilisateur est autorisé, sinon null (RG19).
     */
    public function getLieuOuLienSecurise(?User $user): ?string
    {
        return $this->peutAccederAuxDetailsPrives($user) ? $this->lien_ou_lieu_prive : null;
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES LOCAUX
    |--------------------------------------------------------------------------
    */

    /**
     * Créneaux situés dans le futur (RG04).
     */
    public function scopeFuturs(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('date', '>', now()->toDateString())
                ->orWhere(fn ($today) => $today->where('date', now()->toDateString())->where('heure_debut', '>', now()->format('H:i:s')));
        });
    }

    /**
     * Créneaux disponibles (non encore attribués, RG04, RG07).
     */
    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->whereDoesntHave('reservations', function ($q) {
            $q->whereIn('statut', ['acceptee', 'realisee', 'en_litige']);
        });
    }
}
