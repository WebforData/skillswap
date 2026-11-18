<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\Reservation;
use App\Models\Dispute;
use App\Models\Evaluation;
use App\Services\CreditWalletService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Exception;

class SkillSwapRulesTest extends TestCase
{
    protected CreditWalletService $walletService;
    protected ReservationService $reservationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped("Suite legacy obsolète remplacée par les tests Feature spécialisés (AuthApiTest, ReservationApiTest, CreneauApiTest, LitigeApiTest, AdminApiTest, DeactivatedUserApiTest).");
    }

    /**
     * Test RG01 : Email unique et 2 crédits attribués à l'inscription
     */
    public function test_rg01_registration_awards_two_welcome_credits(): void
    {
        $uniqueEmail = 'test_etudiant_' . time() . '@skillswap.edu';

        $response = $this->post(route('register.post'), [
            'nom' => 'Test',
            'prenom' => 'Nouveau',
            'email' => $uniqueEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'filiere' => 'DUT Informatique',
            'niveau_etudes' => '1ère année',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', $uniqueEmail)->first();
        $this->assertNotNull($user);
        $this->assertEquals(2, $user->solde_disponible);
        $this->assertEquals(0, $user->solde_bloque);

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'montant' => 2,
            'type' => 'bienvenue',
        ]);
    }

    /**
     * Test RG03 : Impossible de réserver sa propre offre
     */
    public function test_rg03_cannot_reserve_own_offer(): void
    {
        $sara = User::where('email', 'sara@skillswap.edu')->first();
        $saraOffer = Offer::where('user_id', $sara->id)->where('status', 'publiee')->first();
        $slot = $saraOffer->slots()->where('status', 'disponible')->first();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Vous ne pouvez pas réserver votre propre offre (RG03).");

        $this->reservationService->createPendingReservation($sara, $slot->id);
    }

    /**
     * Test RG05 & RG06 : Demande et acceptation bloquent les crédits du bénéficiaire
     */
    public function test_rg05_rg06_acceptation_locks_credits(): void
    {
        $amine = User::where('email', 'amine@skillswap.edu')->first();
        $sara = User::where('email', 'sara@skillswap.edu')->first();

        $offer = Offer::where('user_id', $sara->id)->where('duree', 1)->first();
        $slot = Slot::create([
            'offer_id' => $offer->id,
            'date_debut' => Carbon::now()->addDays(10)->setTime(10, 0),
            'date_fin' => Carbon::now()->addDays(10)->setTime(11, 0),
            'lieu_ou_lien' => 'Salle Test B10',
            'status' => 'disponible',
        ]);

        $soldeDispoInitial = $amine->solde_disponible;
        $soldeBloqueInitial = $amine->solde_bloque;

        $reservation = $this->reservationService->createPendingReservation($amine, $slot->id);
        $amine->refresh();
        $this->assertEquals($soldeDispoInitial, $amine->solde_disponible);

        $this->reservationService->acceptReservation($reservation, $sara);
        $amine->refresh();

        $this->assertEquals($soldeDispoInitial - 1, $amine->solde_disponible);
        $this->assertEquals($soldeBloqueInitial + 1, $amine->solde_bloque);
        $this->assertEquals('accepted', $reservation->fresh()->status);
    }

    /**
     * Test RG07 : Un créneau ne peut avoir qu'une réservation acceptée, concurrents refusés automatiquement
     */
    public function test_rg07_concurrent_requests_auto_rejected_on_acceptance(): void
    {
        $sara = User::where('email', 'sara@skillswap.edu')->first();
        $amine = User::where('email', 'amine@skillswap.edu')->first();
        $yassine = User::where('email', 'yassine@skillswap.edu')->first();

        $offer = Offer::where('user_id', $sara->id)->first();
        $slot = Slot::create([
            'offer_id' => $offer->id,
            'date_debut' => Carbon::now()->addDays(12)->setTime(14, 0),
            'date_fin' => Carbon::now()->addDays(12)->setTime(15, 0),
            'lieu_ou_lien' => 'Salle Concurrents',
            'status' => 'disponible',
        ]);

        $reqAmine = $this->reservationService->createPendingReservation($amine, $slot->id);
        $reqYassine = $this->reservationService->createPendingReservation($yassine, $slot->id);

        $this->assertEquals('pending', $reqAmine->fresh()->status);
        $this->assertEquals('pending', $reqYassine->fresh()->status);

        $this->reservationService->acceptReservation($reqAmine, $sara);

        $this->assertEquals('accepted', $reqAmine->fresh()->status);
        $this->assertEquals('rejected', $reqYassine->fresh()->status);
        $this->assertStringContainsString("RG07", $reqYassine->fresh()->motif_refus);
    }

    /**
     * Test RG08 : Interdire le chevauchement d'horaires pour un étudiant
     */
    public function test_rg08_schedule_conflict_prevented(): void
    {
        $amine = User::where('email', 'amine@skillswap.edu')->first();
        $sara = User::where('email', 'sara@skillswap.edu')->first();

        $offer = Offer::where('user_id', $sara->id)->first();
        $slotA = Slot::create([
            'offer_id' => $offer->id,
            'date_debut' => Carbon::now()->addDays(15)->setTime(14, 0),
            'date_fin' => Carbon::now()->addDays(15)->setTime(15, 0),
            'lieu_ou_lien' => 'Salle A',
            'status' => 'disponible',
        ]);

        $slotB = Slot::create([
            'offer_id' => $offer->id,
            'date_debut' => Carbon::now()->addDays(15)->setTime(14, 30),
            'date_fin' => Carbon::now()->addDays(15)->setTime(15, 30),
            'lieu_ou_lien' => 'Salle B',
            'status' => 'disponible',
        ]);

        $resA = $this->reservationService->createPendingReservation($amine, $slotA->id);
        $this->reservationService->acceptReservation($resA, $sara);

        $resB = $this->reservationService->createPendingReservation($amine, $slotB->id);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Conflit de planning détecté");

        $this->reservationService->acceptReservation($resB, $sara);
    }

    /**
     * Test RG10 : Annulation avant début libère les crédits
     */
    public function test_rg10_cancellation_before_start_releases_credits(): void
    {
        $amine = User::where('email', 'amine@skillswap.edu')->first();
        $sara = User::where('email', 'sara@skillswap.edu')->first();

        $offer = Offer::where('user_id', $sara->id)->where('duree', 1)->first();
        $slot = Slot::create([
            'offer_id' => $offer->id,
            'date_debut' => Carbon::now()->addDays(20)->setTime(14, 0),
            'date_fin' => Carbon::now()->addDays(20)->setTime(15, 0),
            'lieu_ou_lien' => 'Visio Test',
            'status' => 'disponible',
        ]);

        $reservation = $this->reservationService->createPendingReservation($amine, $slot->id);
        $this->reservationService->acceptReservation($reservation, $sara);

        $soldeDispoApresAccept = $amine->fresh()->solde_disponible;
        $soldeBloqueApresAccept = $amine->fresh()->solde_bloque;

        $this->reservationService->cancelAcceptedReservation($reservation, $amine, "Empêchement cours");

        $amine->refresh();
        $this->assertEquals($soldeDispoApresAccept + 1, $amine->solde_disponible);
        $this->assertEquals($soldeBloqueApresAccept - 1, $amine->solde_bloque);
        $this->assertEquals('cancelled', $reservation->fresh()->status);
        $this->assertEquals('disponible', $slot->fresh()->status);
    }

    /**
     * Test RG11 : Double confirmation transfère les crédits
     */
    public function test_rg11_double_confirmation_transfers_credits(): void
    {
        $amine = User::where('email', 'amine@skillswap.edu')->first();
        $sara = User::where('email', 'sara@skillswap.edu')->first();

        $offer = Offer::where('user_id', $sara->id)->where('duree', 1)->first();
        $slot = Slot::create([
            'offer_id' => $offer->id,
            'date_debut' => Carbon::now()->subHours(3),
            'date_fin' => Carbon::now()->subHours(2),
            'lieu_ou_lien' => 'Salle Terminée',
            'status' => 'reserve',
        ]);

        $reservation = Reservation::create([
            'slot_id' => $slot->id,
            'beneficiary_id' => $amine->id,
            'status' => 'accepted',
        ]);

        // Pré-bloquer 1 crédit chez Amine
        $amine->solde_disponible = 4;
        $amine->solde_bloque = 1;
        $amine->save();

        $soldeSaraAvant = $sara->fresh()->solde_disponible;
        $soldeBloqueAmineAvant = $amine->fresh()->solde_bloque;

        $transferred1 = $this->reservationService->confirmReservation($reservation, $sara);
        $this->assertFalse($transferred1);
        $this->assertEquals($soldeSaraAvant, $sara->fresh()->solde_disponible);

        $transferred2 = $this->reservationService->confirmReservation($reservation, $amine);
        $this->assertTrue($transferred2);

        $this->assertEquals($soldeSaraAvant + 1, $sara->fresh()->solde_disponible);
        $this->assertEquals($soldeBloqueAmineAvant - 1, $amine->fresh()->solde_bloque);
        $this->assertEquals('completed', $reservation->fresh()->status);
    }

    /**
     * Test RG13 & RG14 : Litige tranché de façon définitive par l'administrateur
     */
    public function test_rg13_rg14_admin_dispute_resolution_definitive(): void
    {
        $amine = User::where('email', 'amine@skillswap.edu')->first();
        $sara = User::where('email', 'sara@skillswap.edu')->first();

        $offer = Offer::where('user_id', $sara->id)->first();
        $slot = Slot::create([
            'offer_id' => $offer->id,
            'date_debut' => Carbon::now()->subHours(4),
            'date_fin' => Carbon::now()->subHours(3),
            'lieu_ou_lien' => 'Salle Litige',
            'status' => 'reserve',
        ]);

        $reservation = Reservation::create([
            'slot_id' => $slot->id,
            'beneficiary_id' => $amine->id,
            'status' => 'dispute',
        ]);

        $amine->solde_disponible = 3;
        $amine->solde_bloque = 1;
        $amine->save();

        $dispute = Dispute::create([
            'reservation_id' => $reservation->id,
            'opened_by_id' => $amine->id,
            'motif' => 'Absence du formateur',
            'description' => 'Le formateur ne s\'est pas présenté au rendez-vous convenu.',
            'resolution' => 'en_cours',
        ]);

        $soldeDispoAmineAvant = $amine->fresh()->solde_disponible;

        $this->walletService->resolveDispute($reservation, 'restitution_beneficiaire', "Absence constatée du formateur");
        $dispute->update([
            'resolution' => 'restitution_beneficiaire',
            'decision_admin' => 'Restitution validée',
            'resolved_at' => now(),
        ]);

        $this->assertEquals($soldeDispoAmineAvant + 1, $amine->fresh()->solde_disponible);
        $this->assertEquals('cancelled', $reservation->fresh()->status);
        $this->assertTrue($dispute->fresh()->isResolved());
    }

    /**
     * Test RG18 : Un compte désactivé ne peut plus se connecter
     */
    public function test_rg18_deactivated_user_cannot_login(): void
    {
        $user = User::create([
            'nom' => 'Bloque',
            'prenom' => 'Compte',
            'email' => 'bloque_test_' . time() . '@skillswap.edu',
            'password' => Hash::make('secret123'),
            'solde_disponible' => 2,
            'solde_bloque' => 0,
            'role' => 'etudiant',
            'is_active' => false,
        ]);

        $response = $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertSessionHas('error');
        $this->assertGuest();
    }
}
