<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\Offre;
use App\Models\Portefeuille;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CreditService;
use App\Services\CreneauService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreneauApiTest extends TestCase
{
    protected CreditService $creditService;
    protected ReservationService $reservationService;
    protected CreneauService $creneauService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createTestSchema();

        $this->creditService = new CreditService();
        $this->reservationService = new ReservationService($this->creditService);
        $this->creneauService = new CreneauService($this->reservationService);
    }

    private function createTestSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('etudiant');
            $table->string('statut')->default('actif');
            $table->string('filiere')->nullable();
            $table->text('competences_proposees')->nullable();
            $table->text('competences_recherchees')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('portefeuilles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->unique()->constrained('users');
            $table->integer('solde_disponible')->default(0);
            $table->integer('credits_bloques')->default(0);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->timestamps();
        });

        Schema::create('offres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('users');
            $table->foreignId('categorie_id')->constrained('categories');
            $table->string('titre');
            $table->string('competence');
            $table->text('description');
            $table->string('niveau')->default('debutant');
            $table->tinyInteger('duree')->default(1);
            $table->string('mode')->default('presentiel');
            $table->string('statut')->default('publiee');
            $table->timestamps();
        });

        Schema::create('creneaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offre_id')->constrained('offres');
            $table->date('date');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('lieu_public');
            $table->text('lien_ou_lieu_prive');
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creneau_id')->constrained('creneaux');
            $table->foreignId('beneficiaire_id')->constrained('users');
            $table->dateTime('date_demande');
            $table->string('statut')->default('en_attente');
            $table->tinyInteger('cout_credits')->default(1);
            $table->text('motif_refus')->nullable();
            $table->boolean('confirmation_intervenant')->default(false);
            $table->dateTime('date_confirmation_intervenant')->nullable();
            $table->boolean('confirmation_beneficiaire')->default(false);
            $table->dateTime('date_confirmation_beneficiaire')->nullable();
            $table->timestamps();
        });

        Schema::create('mouvements_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portefeuille_id')->constrained('portefeuilles');
            $table->foreignId('reservation_id')->nullable()->constrained('reservations');
            $table->integer('quantite');
            $table->string('type');
            $table->dateTime('date_mouvement');
            $table->timestamps();
        });

        Schema::create('litiges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained('reservations');
            $table->foreignId('declarant_id')->constrained('users');
            $table->foreignId('admin_id')->nullable()->constrained('users');
            $table->text('description');
            $table->string('statut')->default('ouvert');
            $table->string('decision')->nullable();
            $table->text('motif_decision')->nullable();
            $table->timestamp('date_resolution')->nullable();
            $table->timestamps();
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations');
            $table->foreignId('auteur_id')->constrained('users');
            $table->foreignId('cible_id')->constrained('users');
            $table->tinyInteger('note');
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });
    }

    private function creerUtilisateur(string $email, string $role = 'etudiant', string $statut = 'actif'): User
    {
        $user = new User();
        $user->forceFill([
            'nom' => 'Nom ' . substr(md5($email), 0, 4),
            'prenom' => 'Prenom',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
            'statut' => $statut,
        ]);
        $user->save();

        $portefeuille = new Portefeuille();
        $portefeuille->forceFill([
            'etudiant_id' => $user->id,
            'solde_disponible' => 5,
            'credits_bloques' => 0,
        ]);
        $portefeuille->save();

        return $user;
    }

    private function creerOffre(User $intervenant, int $duree = 1, string $statut = 'publiee'): Offre
    {
        $cat = Categorie::firstOrCreate(['nom' => 'Informatique']);

        $offre = new Offre();
        $offre->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $cat->id,
            'titre' => 'Tutorat API REST',
            'competence' => 'PHP/Laravel',
            'description' => 'Cours de programmation',
            'niveau' => 'intermediaire',
            'duree' => $duree,
            'mode' => 'presentiel',
            'statut' => $statut,
        ]);
        $offre->save();

        return $offre;
    }

    /**
     * Test 1 : POST /api/offres/{id}/creneaux - Succès 201
     */
    public function test_ajout_creneau_avec_succes(): void
    {
        $intervenant = $this->creerUtilisateur('prof_api@univ.fr');
        $offre = $this->creerOffre($intervenant, 1);

        Sanctum::actingAs($intervenant);

        $dateFuture = Carbon::now()->addDays(2)->toDateString();

        $response = $this->postJson("/api/offres/{$offre->id}/creneaux", [
            'date' => $dateFuture,
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Salle B101',
            'lien_ou_lieu_prive' => 'Bâtiment B, 1er étage',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('creneau.lieu_public', 'Salle B101')
            ->assertJsonPath('creneau.heure_debut', '14:00:00')
            ->assertJsonPath('creneau.heure_fin', '15:00:00');

        $this->assertDatabaseHas('creneaux', [
            'offre_id' => $offre->id,
            'lieu_public' => 'Salle B101',
        ]);
    }

    /**
     * Test 2 : POST /api/offres/{id}/creneaux - 401 si non authentifié
     */
    public function test_ajout_creneau_rejet_non_authentifie(): void
    {
        $intervenant = $this->creerUtilisateur('owner_api@univ.fr');
        $offre = $this->creerOffre($intervenant, 1);

        $response = $this->postJson("/api/offres/{$offre->id}/creneaux", [
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Salle B101',
            'lien_ou_lieu_prive' => 'Bâtiment B, 1er étage',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test 3 : POST /api/offres/{id}/creneaux - 403 si un tiers tente d'ajouter un créneau sur l'offre
     */
    public function test_ajout_creneau_rejet_intervenant_non_proprietaire(): void
    {
        $intervenant = $this->creerUtilisateur('owner2@univ.fr');
        $tiers = $this->creerUtilisateur('tiers2@univ.fr');
        $offre = $this->creerOffre($intervenant, 1);

        Sanctum::actingAs($tiers);

        $response = $this->postJson("/api/offres/{$offre->id}/creneaux", [
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Salle B101',
            'lien_ou_lieu_prive' => 'Secret',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 4 : POST /api/offres/{id}/creneaux - 403 si compte intervenant désactivé (RG18)
     */
    public function test_ajout_creneau_rejet_compte_desactive(): void
    {
        $intervenant = $this->creerUtilisateur('desactive@univ.fr', 'etudiant', 'desactive');
        $offre = $this->creerOffre($intervenant, 1);

        Sanctum::actingAs($intervenant);

        $response = $this->postJson("/api/offres/{$offre->id}/creneaux", [
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Salle B101',
            'lien_ou_lieu_prive' => 'Secret',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 5 : POST /api/offres/{id}/creneaux - 422 si durée incohérente avec l'offre (RG02)
     */
    public function test_ajout_creneau_rejet_duree_incompatible(): void
    {
        $intervenant = $this->creerUtilisateur('duree_err@univ.fr');
        $offre = $this->creerOffre($intervenant, 1); // 1h

        Sanctum::actingAs($intervenant);

        // Envoi d'un créneau de 2 heures pour une offre de 1 heure
        $response = $this->postJson("/api/offres/{$offre->id}/creneaux", [
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '16:00',
            'lieu_public' => 'Salle B101',
            'lien_ou_lieu_prive' => 'Secret',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test 6 : POST /api/offres/{id}/creneaux - 409 si chevauchement horaire intervenant (RG08)
     */
    public function test_ajout_creneau_rejet_chevauchement(): void
    {
        $intervenant = $this->creerUtilisateur('chevauche_api@univ.fr');
        $offre1 = $this->creerOffre($intervenant, 1);
        $offre2 = $this->creerOffre($intervenant, 1);

        $dateFuture = Carbon::now()->addDays(3)->toDateString();

        // Premier créneau 14h-15h
        Creneau::create([
            'offre_id' => $offre1->id,
            'date' => $dateFuture,
            'heure_debut' => '14:00:00',
            'heure_fin' => '15:00:00',
            'lieu_public' => 'Salle 1',
            'lien_ou_lieu_prive' => 'Secret 1',
        ]);

        Sanctum::actingAs($intervenant);

        // Tentative d'ajouter 14:30-15:30 sur offre2
        $response = $this->postJson("/api/offres/{$offre2->id}/creneaux", [
            'date' => $dateFuture,
            'heure_debut' => '14:30',
            'heure_fin' => '15:30',
            'lieu_public' => 'Salle 2',
            'lien_ou_lieu_prive' => 'Secret 2',
        ]);

        $response->assertStatus(409);
    }

    /**
     * Test 7 : PUT /api/creneaux/{id} - Succès 200 modification créneau libre (RG17)
     */
    public function test_modification_creneau_avec_succes(): void
    {
        $intervenant = $this->creerUtilisateur('prof_modif_api@univ.fr');
        $offre = $this->creerOffre($intervenant, 1);

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(3)->toDateString(),
            'heure_debut' => '14:00:00',
            'heure_fin' => '15:00:00',
            'lieu_public' => 'Salle Ancienne',
            'lien_ou_lieu_prive' => 'Secret Ancien',
        ]);

        Sanctum::actingAs($intervenant);

        $response = $this->putJson("/api/creneaux/{$creneau->id}", [
            'heure_debut' => '16:00',
            'heure_fin' => '17:00',
            'lieu_public' => 'Salle Nouvelle',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('creneau.heure_debut', '16:00:00')
            ->assertJsonPath('creneau.heure_fin', '17:00:00')
            ->assertJsonPath('creneau.lieu_public', 'Salle Nouvelle');

        $this->assertDatabaseHas('creneaux', [
            'id' => $creneau->id,
            'heure_debut' => '16:00:00',
            'lieu_public' => 'Salle Nouvelle',
        ]);
    }

    /**
     * Test 8 : PUT /api/creneaux/{id} - 409 si créneau déjà attribué (RG07)
     */
    public function test_modification_creneau_rejet_attribue(): void
    {
        $intervenant = $this->creerUtilisateur('prof_attrib_api@univ.fr');
        $beneficiaire = $this->creerUtilisateur('eleve_attrib_api@univ.fr');
        $offre = $this->creerOffre($intervenant, 1);

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(4)->toDateString(),
            'heure_debut' => '14:00:00',
            'heure_fin' => '15:00:00',
            'lieu_public' => 'Salle',
            'lien_ou_lieu_prive' => 'Secret',
        ]);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        Sanctum::actingAs($intervenant);

        $response = $this->putJson("/api/creneaux/{$creneau->id}", [
            'heure_debut' => '17:00',
            'heure_fin' => '18:00',
        ]);

        $response->assertStatus(409);
    }

    /**
     * Test 9 : DELETE /api/creneaux/{id} - Succès 200 suppression créneau libre (RG17)
     */
    public function test_suppression_creneau_libre_avec_succes(): void
    {
        $intervenant = $this->creerUtilisateur('prof_del_api@univ.fr');
        $offre = $this->creerOffre($intervenant, 1);

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(3)->toDateString(),
            'heure_debut' => '14:00:00',
            'heure_fin' => '15:00:00',
            'lieu_public' => 'Salle',
            'lien_ou_lieu_prive' => 'Secret',
        ]);

        Sanctum::actingAs($intervenant);

        $response = $this->deleteJson("/api/creneaux/{$creneau->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Le créneau a été supprimé avec succès.');

        $this->assertDatabaseMissing('creneaux', ['id' => $creneau->id]);
    }

    /**
     * Test 10 : DELETE /api/creneaux/{id} - 409 si créneau attribué (RG07)
     */
    public function test_suppression_creneau_rejet_attribue(): void
    {
        $intervenant = $this->creerUtilisateur('prof_del_no@univ.fr');
        $beneficiaire = $this->creerUtilisateur('eleve_del_no@univ.fr');
        $offre = $this->creerOffre($intervenant, 1);

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(4)->toDateString(),
            'heure_debut' => '14:00:00',
            'heure_fin' => '15:00:00',
            'lieu_public' => 'Salle',
            'lien_ou_lieu_prive' => 'Secret',
        ]);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        Sanctum::actingAs($intervenant);

        $response = $this->deleteJson("/api/creneaux/{$creneau->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('creneaux', ['id' => $creneau->id]);
    }

    /**
     * Test 11 : GET /api/planning - Récupération du planning consolidé
     */
    public function test_planning_acces_authentifie_et_filtres(): void
    {
        $intervenant = $this->creerUtilisateur('prof_plan_api@univ.fr');
        $beneficiaire = $this->creerUtilisateur('eleve_plan_api@univ.fr');
        $offre = $this->creerOffre($intervenant, 1);

        $dateFuture = Carbon::now()->addDays(2)->toDateString();

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => $dateFuture,
            'heure_debut' => '10:00:00',
            'heure_fin' => '11:00:00',
            'lieu_public' => 'Salle 102',
            'lien_ou_lieu_prive' => 'Lien Zoom https://univ.zoom.us/j/12345',
        ]);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        // Vérification pour le bénéficiaire
        Sanctum::actingAs($beneficiaire);
        $respBen = $this->getJson('/api/planning');
        $respBen->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('planning.0.role', 'beneficiaire')
            ->assertJsonPath('planning.0.statut', 'acceptee')
            ->assertJsonPath('planning.0.creneau.lieu_ou_lien_prive', 'Lien Zoom https://univ.zoom.us/j/12345');

        // Vérification pour l'intervenant
        Sanctum::actingAs($intervenant);
        $respInt = $this->getJson('/api/planning?filtre=a_venir');
        $respInt->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('planning.0.role', 'intervenant')
            ->assertJsonPath('planning.0.statut', 'acceptee');

        // Vérification avec filtre historique (ne doit rien retourner car la séance est future)
        $respHist = $this->getJson('/api/planning?filtre=historique');
        $respHist->assertStatus(200)
            ->assertJsonPath('total', 0);
    }
}
