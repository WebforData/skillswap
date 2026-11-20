<?php

namespace App\Models;

use App\Services\ReservationNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(fn (self $reservation) => app(ReservationNotificationService::class)->record($reservation));
    }

    /**
     * Nom de la table associée.
     */
    protected $table = 'reservations';

    /**
     * Valeurs par défaut des attributs du modèle.
     */
    protected $attributes = [
        'statut' => 'en_attente',
        'cout_credits' => 1,
        'confirmation_intervenant' => false,
        'confirmation_beneficiaire' => false,
    ];

    /**
     * Attributs assignables en masse.
     * SÉCURITÉ : 'beneficiaire_id', 'statut', 'cout_credits', 'motif_refus' ainsi que les confirmations
     * et colonnes générées sont STRICTEMENT protégés contre le mass-assignment.
     * Seul 'creneau_id' peut être transmis lors de la formulation initiale de la demande.
     */
    protected $fillable = [
        'creneau_id',
    ];

    /**
     * Typage des attributs.
     */
    protected function casts(): array
    {
        return [
            'creneau_id' => 'integer',
            'beneficiaire_id' => 'integer',
            'date_demande' => 'datetime',
            'cout_credits' => 'integer',
            'confirmation_intervenant' => 'boolean',
            'date_confirmation_intervenant' => 'datetime',
            'confirmation_beneficiaire' => 'boolean',
            'date_confirmation_beneficiaire' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Créneau horaire sur lequel porte cette demande de séance (Inverse 1-to-Many).
     */
    public function creneau(): BelongsTo
    {
        return $this->belongsTo(Creneau::class, 'creneau_id');
    }

    /**
     * Étudiant bénéficiaire de la séance (Inverse 1-to-Many).
     */
    public function beneficiaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiaire_id');
    }

    /**
     * Mouvements de crédits engendrés par cette réservation (1-to-Many).
     */
    public function mouvementsCredits(): HasMany
    {
        return $this->hasMany(MouvementCredit::class, 'reservation_id');
    }

    /**
     * Évaluations déposées pour cette séance (1-to-Many, au maximum 2 : 1 par participant, RG15).
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'reservation_id');
    }

    /**
     * Litige éventuel ouvert sur cette réservation (1-to-1 unique, RG14).
     */
    public function litige(): HasOne
    {
        return $this->hasOne(Litige::class, 'reservation_id');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSEURS & LOGIQUE DE RÔLE (INTERVENANT vs BÉNÉFICIAIRE)
    |--------------------------------------------------------------------------
    */

    /**
     * Récupère l'intervenant de la séance (propriétaire de l'offre liée au créneau).
     * Navigation : Reservation -> Creneau -> Offre -> Intervenant (User).
     */
    public function getIntervenantAttribute(): ?User
    {
        return $this->creneau?->offre?->intervenant;
    }

    /**
     * Identifiant de l'intervenant.
     */
    public function getIntervenantIdAttribute(): ?int
    {
        return $this->creneau?->offre?->etudiant_id;
    }

    /**
     * Vérifie si l'utilisateur donné est le bénéficiaire de cette réservation.
     */
    public function isBeneficiaire(User $user): bool
    {
        return (int) $this->beneficiaire_id === (int) $user->id;
    }

    /**
     * Vérifie si l'utilisateur donné est l'intervenant de cette réservation.
     */
    public function isIntervenant(User $user): bool
    {
        return (int) $this->getIntervenantIdAttribute() === (int) $user->id;
    }

    /**
     * Vérifie si l'utilisateur donné est l'un des deux participants de la réservation.
     */
    public function isParticipant(User $user): bool
    {
        return $this->isBeneficiaire($user) || $this->isIntervenant($user);
    }

    /**
     * Vérifie si les deux participants ont validé la séance (RG11).
     */
    public function isDoublementConfirmee(): bool
    {
        return $this->confirmation_intervenant && $this->confirmation_beneficiaire;
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES LOCAUX
    |--------------------------------------------------------------------------
    */

    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->where('statut', 'en_attente');
    }

    public function scopeAcceptees(Builder $query): Builder
    {
        return $query->where('statut', 'acceptee');
    }

    public function scopeRealisees(Builder $query): Builder
    {
        return $query->where('statut', 'realisee');
    }

    public function scopeEnLitige(Builder $query): Builder
    {
        return $query->where('statut', 'en_litige');
    }
}
