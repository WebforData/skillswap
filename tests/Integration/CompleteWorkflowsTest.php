<?php

namespace Tests\Integration;

use App\Models\Creneau;
use App\Models\MouvementCredit;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CreneauService;
use App\Services\ReservationService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CompleteWorkflowsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $database = getenv('TEST_MYSQL_DATABASE');
        if (! $database) {
            $this->markTestSkipped('Set TEST_MYSQL_DATABASE to an isolated database ending in _test.');
        }
        if (! preg_match('/^skillswap_[a-z0-9_]*test$/', $database)) {
            throw new \RuntimeException('Refusing to reset a non-test database.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database]);
        DB::purge('mysql');
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->seed();
    }

    private function student(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_seed_is_repeatable_and_assigns_exactly_two_credits(): void
    {
        $this->seed();
        $this->assertSame(4, User::count());
        $this->assertSame(3, MouvementCredit::where('type', 'bienvenue')->count());
        $this->postJson('/api/auth/login', ['email' => 'admin@skillswap.edu', 'password' => 'password'])->assertOk()->assertJsonPath('user.role', 'administrateur');
        $this->assertSame(2, $this->student('amine@skillswap.edu')->portefeuille->solde_disponible);
    }

    public function test_profile_suggestions_history_categories_and_private_slot_contract(): void
    {
        $amine = $this->student('amine@skillswap.edu');
        Sanctum::actingAs($amine);
        $this->putJson('/api/auth/profile', ['nom' => 'Benali', 'prenom' => 'Amine', 'email' => $amine->email, 'competences_recherchees' => 'Python', 'filiere' => 'GI'])->assertOk();
        $this->getJson('/api/suggestions')->assertOk()->assertJsonPath('offres.0.competence', 'Python');
        $this->getJson('/api/wallet/history')->assertOk()->assertJsonPath('data.0.type', 'bienvenue');
        $this->getJson('/api/categories')->assertOk()->assertJsonCount(5, 'categories');
        Sanctum::actingAs($this->student('sara@skillswap.edu'));
        $this->getJson('/api/offres/mes-offres')->assertOk()->assertJsonPath('data.0.creneaux.0.lieu_ou_lien_prive', 'Salle B204');
        $this->getJson('/api/offres?date='.now()->addDays(8)->toDateString())->assertOk()->assertJsonCount(0, 'data');
        $slot = Creneau::whereHas('offre', fn ($q) => $q->where('competence', 'Python'))->firstOrFail();
        $this->assertSame(now()->addDays(2)->toDateString(), $this->getJson('/api/offres/mes-offres')->json('data.0.creneaux.0.date'));
        $this->assertNotNull($slot);
    }

    public function test_full_reservation_settlement_review_and_notifications(): void
    {
        $sara = $this->student('sara@skillswap.edu');
        $amine = $this->student('amine@skillswap.edu');
        $slot = $sara->offres()->firstOrFail()->creneaux()->firstOrFail();
        Sanctum::actingAs($amine);
        $r = $this->postJson('/api/reservations', ['creneau_id' => $slot->id])->assertCreated()->json('reservation.id');
        $this->postJson('/api/reservations', ['creneau_id' => $slot->id])->assertStatus(422);
        Sanctum::actingAs($sara);
        $this->postJson("/api/reservations/$r/accepter")->assertOk();
        $this->assertSame(1, $amine->portefeuille()->first()->credits_bloques);
        $this->postJson("/api/reservations/$r/confirmer")->assertStatus(422);
        $this->travelTo($slot->date->setTimeFromTimeString($slot->heure_fin)->addMinute());
        $this->postJson("/api/reservations/$r/confirmer")->assertOk();
        Sanctum::actingAs($amine);
        $this->postJson("/api/reservations/$r/confirmer")->assertOk()->assertJsonPath('reservation.statut', 'realisee');
        $this->postJson('/api/evaluations', ['reservation_id' => $r, 'note' => 5, 'commentaire' => 'Séance très utile'])->assertCreated();
        $this->postJson('/api/evaluations', ['reservation_id' => $r, 'note' => 4])->assertStatus(422);
        $transfers = MouvementCredit::where('reservation_id', $r)->where('type', 'transfert_gain')->get();
        $this->assertCount(2, $transfers);
        $this->assertSame(0, (int) $transfers->sum('quantite'));
        $this->assertCount(1, $transfers->where('quantite', '>', 0));
        $n = $this->getJson('/api/notifications')->assertOk()->json('data.0.id');
        $this->patchJson("/api/notifications/$n/read")->assertOk();
        $this->assertTrue(Notification::findOrFail($n)->is_read);
        Sanctum::actingAs($sara);
        $this->patchJson("/api/notifications/$n/read")->assertNotFound();
        $this->travelBack();
    }

    public function test_both_parties_can_provide_dispute_evidence_and_settlement_is_final(): void
    {
        $sara = $this->student('sara@skillswap.edu');
        $amine = $this->student('amine@skillswap.edu');
        $slot = $sara->offres()->firstOrFail()->creneaux()->firstOrFail();
        $service = app(ReservationService::class);
        $r = $service->demanderReservation($amine, $slot->id);
        $service->accepterReservation($sara, $r->id);
        $this->travelTo($slot->date->setTimeFromTimeString($slot->heure_fin)->addMinute());
        Sanctum::actingAs($amine);
        $id = $this->postJson("/api/reservations/{$r->id}/litige", ['description' => 'La séance ne correspond pas à la description.'])->assertCreated()->json('litige.id');
        Sanctum::actingAs($sara);
        $this->postJson("/api/litiges/$id/statements", ['message' => 'Voici mon témoignage et les éléments de la séance.'])->assertCreated();
        Sanctum::actingAs($this->student('yassine@skillswap.edu'));
        $this->postJson("/api/litiges/$id/statements", ['message' => 'Je ne suis pas participant.'])->assertForbidden();
        Sanctum::actingAs($this->student('admin@skillswap.edu'));
        $this->getJson("/api/litiges/$id")->assertOk()->assertJsonCount(1, 'litige.statements');
        $payload = ['decision' => 'restitution_beneficiaire', 'motif' => 'Remboursement après examen des témoignages.'];
        $this->postJson("/api/litiges/$id/arbitrer", $payload)->assertOk();
        $this->postJson("/api/litiges/$id/arbitrer", $payload)->assertStatus(422);
        $this->assertSame(2, $amine->portefeuille()->first()->solde_disponible);
        $this->travelBack();
    }

    public function test_concurrent_acceptances_cannot_double_book_a_student(): void
    {
        $sara = $this->student('sara@skillswap.edu');
        $yassine = $this->student('yassine@skillswap.edu');
        $amine = $this->student('amine@skillswap.edu');
        $slot = $sara->offres()->firstOrFail()->creneaux()->firstOrFail();
        $other = app(CreneauService::class)->ajouterCreneau($yassine, $yassine->offres()->firstOrFail()->id, [
            'date' => $slot->date->format('Y-m-d'), 'heure_debut' => '10:00', 'heure_fin' => '11:00',
            'lieu_public' => 'Campus', 'lien_ou_lieu_prive' => 'Salle privée',
        ]);
        $service = app(ReservationService::class);
        $a = $service->demanderReservation($amine, $slot->id);
        $b = $service->demanderReservation($amine, $other->id);
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => getenv('TEST_MYSQL_DATABASE')];
        $one = new Process([PHP_BINARY, 'tests/fixtures/accept-reservation.php', (string) $sara->id, (string) $a->id], base_path(), $env);
        $two = new Process([PHP_BINARY, 'tests/fixtures/accept-reservation.php', (string) $yassine->id, (string) $b->id], base_path(), $env);
        $one->start();
        $two->start();
        $one->wait();
        $two->wait();
        $this->assertTrue($one->isSuccessful(), $one->getErrorOutput());
        $this->assertTrue($two->isSuccessful(), $two->getErrorOutput());
        $outcomes = [$one->getOutput(), $two->getOutput()];
        sort($outcomes);
        $this->assertSame(['accepted', 'schedule_conflict'], $outcomes);
        $this->assertSame(1, Reservation::where('beneficiaire_id', $amine->id)->where('statut', 'acceptee')->count());
        $this->assertSame(1, $amine->portefeuille()->first()->credits_bloques);
    }

    public function test_cancelled_session_history_survives_slot_deletion_attempt_and_slot_can_be_rebooked(): void
    {
        $sara = $this->student('sara@skillswap.edu');
        $amine = $this->student('amine@skillswap.edu');
        $slot = $sara->offres()->firstOrFail()->creneaux()->firstOrFail();
        $service = app(ReservationService::class);
        $reservation = $service->demanderReservation($amine, $slot->id);
        $service->accepterReservation($sara, $reservation->id);
        $service->annulerReservationAcceptee($amine, $reservation->id);
        Sanctum::actingAs($sara);
        $this->deleteJson('/api/creneaux/'.$slot->id)->assertStatus(409);
        $this->assertSame('annulee', $reservation->fresh()->statut);
        $this->assertSame(2, $reservation->mouvementsCredits()->count());
        $replacement = $service->demanderReservation($amine, $slot->id);
        $this->assertSame('en_attente', $replacement->statut);
        $this->assertNotSame($reservation->id, $replacement->id);
    }

    public function test_private_location_is_visible_only_to_session_participants(): void
    {
        $sara = $this->student('sara@skillswap.edu');
        $amine = $this->student('amine@skillswap.edu');
        $admin = $this->student('admin@skillswap.edu');
        $offre = $sara->offres()->firstOrFail();
        $slot = $offre->creneaux()->firstOrFail();
        Sanctum::actingAs($admin);
        $this->getJson('/api/offres/'.$offre->id)->assertOk()->assertJsonPath('offre.creneaux.0.lieu_ou_lien_prive', null);
        $service = app(ReservationService::class);
        $reservation = $service->demanderReservation($amine, $slot->id);
        $service->accepterReservation($sara, $reservation->id);
        $this->getJson('/api/reservations/'.$reservation->id)->assertOk()->assertJsonPath('reservation.creneau.lieu_ou_lien_prive', null);
        Sanctum::actingAs($amine);
        $this->getJson('/api/reservations/'.$reservation->id)->assertOk()->assertJsonPath('reservation.creneau.lieu_ou_lien_prive', 'Salle B204');
    }
}
