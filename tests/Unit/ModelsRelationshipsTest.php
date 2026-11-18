<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Portefeuille;
use App\Models\Categorie;
use App\Models\Offre;
use App\Models\Creneau;
use App\Models\Reservation;
use App\Models\MouvementCredit;
use App\Models\Evaluation;
use App\Models\Litige;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ModelsRelationshipsTest extends TestCase
{
    public function test_user_relationships(): void
    {
        $user = new User();
        $this->assertInstanceOf(HasOne::class, $user->portefeuille());
        $this->assertInstanceOf(HasMany::class, $user->offres());
        $this->assertInstanceOf(HasMany::class, $user->reservationsBeneficiaire());
        $this->assertInstanceOf(HasMany::class, $user->evaluationsRedigees());
        $this->assertInstanceOf(HasMany::class, $user->evaluationsRecues());
        $this->assertInstanceOf(HasMany::class, $user->litigesDeclares());
        $this->assertInstanceOf(HasMany::class, $user->litigesTraites());
    }

    public function test_portefeuille_relationships(): void
    {
        $portefeuille = new Portefeuille();
        $this->assertInstanceOf(BelongsTo::class, $portefeuille->etudiant());
        $this->assertInstanceOf(HasMany::class, $portefeuille->mouvementsCredits());
    }

    public function test_categorie_relationships(): void
    {
        $categorie = new Categorie();
        $this->assertInstanceOf(HasMany::class, $categorie->offres());
    }

    public function test_offre_relationships(): void
    {
        $offre = new Offre();
        $this->assertInstanceOf(BelongsTo::class, $offre->intervenant());
        $this->assertInstanceOf(BelongsTo::class, $offre->categorie());
        $this->assertInstanceOf(HasMany::class, $offre->creneaux());
    }

    public function test_creneau_relationships(): void
    {
        $creneau = new Creneau();
        $this->assertInstanceOf(BelongsTo::class, $creneau->offre());
        $this->assertInstanceOf(HasMany::class, $creneau->reservations());
        $this->assertInstanceOf(HasOne::class, $creneau->reservationAcceptee());
    }

    public function test_reservation_relationships(): void
    {
        $reservation = new Reservation();
        $this->assertInstanceOf(BelongsTo::class, $reservation->creneau());
        $this->assertInstanceOf(BelongsTo::class, $reservation->beneficiaire());
        $this->assertInstanceOf(HasMany::class, $reservation->mouvementsCredits());
        $this->assertInstanceOf(HasMany::class, $reservation->evaluations());
        $this->assertInstanceOf(HasOne::class, $reservation->litige());
    }

    public function test_mouvement_credit_relationships(): void
    {
        $mvt = new MouvementCredit();
        $this->assertInstanceOf(BelongsTo::class, $mvt->portefeuille());
        $this->assertInstanceOf(BelongsTo::class, $mvt->reservation());
    }

    public function test_evaluation_relationships(): void
    {
        $evaluation = new Evaluation();
        $this->assertInstanceOf(BelongsTo::class, $evaluation->reservation());
        $this->assertInstanceOf(BelongsTo::class, $evaluation->auteur());
        $this->assertInstanceOf(BelongsTo::class, $evaluation->cible());
    }

    public function test_litige_relationships(): void
    {
        $litige = new Litige();
        $this->assertInstanceOf(BelongsTo::class, $litige->reservation());
        $this->assertInstanceOf(BelongsTo::class, $litige->declarant());
        $this->assertInstanceOf(BelongsTo::class, $litige->administrateur());
    }
}

