<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Nom de la table associée.
     */
    protected $table = 'users';

    /**
     * Valeurs par défaut des attributs du modèle.
     */
    protected $attributes = [
        'role' => 'etudiant',
        'statut' => 'actif',
    ];

    /**
     * Attributs assignables en masse.
     * SÉCURITÉ : 'role' et 'statut' sont expressément retirés pour empêcher toute élévation
     * de privilèges ou activation non autorisée par mass-assignment.
     */
    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'filiere',
        'competences_proposees',
        'competences_recherchees',
    ];

    /**
     * Attributs masqués lors de la sérialisation (JSON / API).
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Typage des attributs.
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'deleted_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Portefeuille de crédits unique de l'étudiant (1-to-1).
     */
    public function portefeuille(): HasOne
    {
        return $this->hasOne(Portefeuille::class, 'etudiant_id');
    }

    /**
     * Offres proposées par cet utilisateur en tant qu'intervenant (1-to-Many).
     */
    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class, 'etudiant_id');
    }

    /**
     * Réservations demandées par cet utilisateur en tant que bénéficiaire (1-to-Many).
     */
    public function reservationsBeneficiaire(): HasMany
    {
        return $this->hasMany(Reservation::class, 'beneficiaire_id');
    }

    /**
     * Évaluations rédigées par cet utilisateur en tant qu'auteur (1-to-Many).
     */
    public function evaluationsRedigees(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'auteur_id');
    }

    /**
     * Évaluations reçues par cet utilisateur en tant que cible (1-to-Many).
     */
    public function evaluationsRecues(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'cible_id');
    }

    /**
     * Litiges ouverts / déclarés par cet utilisateur (1-to-Many).
     */
    public function litigesDeclares(): HasMany
    {
        return $this->hasMany(Litige::class, 'declarant_id');
    }

    /**
     * Litiges traités par cet utilisateur en tant qu'administrateur (1-to-Many).
     */
    public function litigesTraites(): HasMany
    {
        return $this->hasMany(Litige::class, 'admin_id');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSEURS & LOGIQUE MÉTIER RAPIDE
    |--------------------------------------------------------------------------
    */

    /**
     * Réservations où l'utilisateur est intervenant (via ses offres et créneaux).
     */
    public function reservationsIntervenant()
    {
        return Reservation::whereHas('creneau.offre', function ($query) {
            $query->where('etudiant_id', $this->id);
        });
    }

    /**
     * Alias de compatibilité vers les réservations où l'utilisateur est bénéficiaire.
     */
    public function reservations(): HasMany
    {
        return $this->reservationsBeneficiaire();
    }

    /**
     * Vérifie si l'utilisateur possède le rôle étudiant.
     */
    public function isEtudiant(): bool
    {
        return $this->role === 'etudiant';
    }

    /**
     * Vérifie si l'utilisateur possède le rôle administrateur.
     */
    public function isAdministrateur(): bool
    {
        return $this->role === 'administrateur';
    }

    /**
     * Alias de vérification du rôle administrateur.
     */
    public function isAdmin(): bool
    {
        return $this->isAdministrateur();
    }

    /**
     * Vérifie si le compte est actif (RG18).
     */
    public function isActif(): bool
    {
        return $this->statut === 'actif';
    }

    /**
     * Accesseur is_active (RG18).
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->isActif();
    }
}
