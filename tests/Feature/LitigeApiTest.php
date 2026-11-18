<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\Litige;
use App\Models\MouvementCredit;
use App\Models\Offre;
use App\Models\Portefeuille;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CreditService;
use App\Services\LitigeService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LitigeApiTest extends TestCase
{
    protected CreditService $creditService;
    protected ReservationService $reservationService;
    protected LitigeService $litigeService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createTestSchema();

        $this->creditService = new CreditService();
        $this->reservationService = new ReservationService($this->creditService);
        $this->litigeService = new LitigeService($this->creditService);
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
            $table->string('statut')->default('brouillon');
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
            $table->timestamp('date_demande')->nullable();
            $table->string('statut')->default('en_attente');
            $table->tinyInteger('cout_credits')->default(1);
            $table->text('motif_refus')->nullable();
            $table->boolean('confirmation_intervenant')->default(false);
            $table->timestamp('date_confirmation_intervenant')->nullable();
            $table->boolean('confirmation_beneficiaire')->default(false);
            $table->timestamp('date_confirmation_beneficiaire')->nullable();
            $table->timestamps();
        });

        Schema::create('mouvements_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portefeuille_id')->constrained('portefeuilles');
            $table->foreignId('reservation_id')->nullable()->constrained('reservations');
            $table->timestamp('date_mouvement')->nullable();
            $table->integer('quantite');
            $table->string('type');
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
    }

    private function creerUtilisateur(string $email, string $role = 'etudiant', string $statut = 'actif', int $solde = 5): User
    {
        $user = new User();
        $user->forceFill([
            'nom' => 'Nom' . uniqid(),
            'prenom' => 'Prenom' . uniqid(),
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
            'statut' => $statut,
        ]);
        $user->save();

        if ($role === 'etudiant') {
            $portefeuille = new Portefeuille();
            $portefeuille->forceFill([
                'etudiant_id' => $user->id,
                'solde_disponible' => $solde,
                'credits_bloques' => 0,
            ]);
            $portefeuille->save();
        }

        return $user;
    }

    private function preparerLitigeOuvert(int $cout = 2): array
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 5);
        $admin = $this->creerUtilisateur('admin@univ.fr', 'administrateur', 'actif');

        $cat = Categorie::firstOrCreate(['nom' => 'Informatique']);

        $offre = new Offre();
        $offre->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $cat->id,
            'titre' => 'Initiation IA & LLM',
            'competence' => 'IA',
            'description' => 'Séance IA',
            'niveau' => 'intermediaire',
            'duree' => $cout,
            'mode' => 'distanciel',
            'statut' => 'publiee',
        ]);
        $offre->save();

        $debut = Carbon::now()->addHours(1);
        $fin = (clone $debut)->addHours($cout);

        $creneau = new Creneau();
        $creneau->forceFill([
            'offre_id' => $offre->id,
            'date' => $debut->toDateString(),
            'heure_debut' => $debut->format('H:i:s'),
            'heure_fin' => $fin->format('H:i:s'),
            'lieu_public' => 'En ligne',
            'lien_ou_lieu_prive' => 'https://meet.skillswap.edu/salle-ia',
        ]);
        $creneau->save();

        $reservation = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $reservation->id);

        Carbon::setTestNow(Carbon::now()->addHours(2));
        $litige = $this->reservationService->ouvrirLitige($beneficiaire, $reservation->id, "L'intervenant n'a pas assuré la séance.");
        Carbon::setTestNow();

        return compact('intervenant', 'beneficiaire', 'admin', 'reservation', 'litige');
    }

    /**
     * Test 1 : Rejet d'un appel non authentifié -> HTTP 401
     */
    public function test_api_arbitrage_rejet_non_authentifie(): void
    {
        $response = $this->postJson(route('api.litiges.arbitrer', ['id' => 1]), [
            'decision' => 'transfert_intervenant',
            'motif' => 'Arbitrage légitime',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test 2 : Rejet d'un étudiant non-administrateur -> HTTP 403
     */
    public function test_api_arbitrage_rejet_etudiant_non_admin(): void
    {
        $data = $this->preparerLitigeOuvert();
        $etudiant = $this->creerUtilisateur('etudiant@univ.fr', 'etudiant', 'actif');

        Sanctum::actingAs($etudiant);

        $response = $this->postJson(route('api.litiges.arbitrer', ['id' => $data['litige']->id]), [
            'decision' => 'transfert_intervenant',
            'motif' => 'Tentative frauduleuse',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 3 : Rejet d'un administrateur désactivé -> HTTP 403
     */
    public function test_api_arbitrage_rejet_admin_desactive(): void
    {
        $data = $this->preparerLitigeOuvert();
        $adminBloque = $this->creerUtilisateur('admin_bloque@univ.fr', 'administrateur', 'desactive');

        Sanctum::actingAs($adminBloque);

        $response = $this->postJson(route('api.litiges.arbitrer', ['id' => $data['litige']->id]), [
            'decision' => 'transfert_intervenant',
            'motif' => 'Tentative compte suspendu',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 4 : Rejet d'une décision invalide -> HTTP 422
     */
    public function test_api_arbitrage_rejet_decision_invalide(): void
    {
        $data = $this->preparerLitigeOuvert();

        Sanctum::actingAs($data['admin']);

        $response = $this->postJson(route('api.litiges.arbitrer', ['id' => $data['litige']->id]), [
            'decision' => 'annuler_litige_invalide',
            'motif' => 'Motif correct',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['decision']);
    }

    /**
     * Test 5 : Rejet d'un motif manquant ou trop court -> HTTP 422
     */
    public function test_api_arbitrage_rejet_motif_trop_court(): void
    {
        $data = $this->preparerLitigeOuvert();

        Sanctum::actingAs($data['admin']);

        $response = $this->postJson(route('api.litiges.arbitrer', ['id' => $data['litige']->id]), [
            'decision' => 'transfert_intervenant',
            'motif' => 'Non', // Moins de 5 caractères
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['motif']);
    }

    /**
     * Test 6 : Litige inexistant -> HTTP 404
     */
    public function test_api_arbitrage_identifiant_inexistant(): void
    {
        $admin = $this->creerUtilisateur('admin@univ.fr', 'administrateur', 'actif');

        Sanctum::actingAs($admin);

        $response = $this->postJson(route('api.litiges.arbitrer', ['id' => 99999]), [
            'decision' => 'transfert_intervenant',
            'motif' => 'Dossier fantôme',
        ]);

        $response->assertStatus(404);
    }

    /**
     * Test 7 : Arbitrage réussi favorable à l'intervenant -> HTTP 200 et transfert
     */
    public function test_api_arbitrage_reussi_transfert_intervenant(): void
    {
        $data = $this->preparerLitigeOuvert(2);

        Sanctum::actingAs($data['admin']);

        $response = $this->postJson(route('api.litiges.arbitrer', ['id' => $data['litige']->id]), [
            'decision' => 'transfert_intervenant',
            'motif' => 'L\'intervenant a fourni les captures d\'écran de la séance.',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'litige' => [
                    'id',
                    'reservation_id',
                    'declarant_id',
                    'admin_id',
                    'statut',
                    'decision',
                    'motif_decision',
                    'date_resolution',
                ],
            ])
            ->assertJson([
                'litige' => [
                    'statut' => 'resolu',
                    'decision' => 'transfert_intervenant',
                    'admin_id' => $data['admin']->id,
                ],
            ]);

        // Vérification financière
        $walletInter = Portefeuille::where('etudiant_id', $data['intervenant']->id)->first();
        $this->assertEquals(7, $walletInter->solde_disponible); // 5 + 2

        // RG14 : La réservation associée passe à 'realisee'
        $this->assertEquals('realisee', $data['reservation']->fresh()->statut);
    }

    /**
     * Test 8 : Arbitrage réussi favorable au bénéficiaire -> HTTP 200 et restitution
     */
    public function test_api_arbitrage_reussi_restitution_beneficiaire(): void
    {
        $data = $this->preparerLitigeOuvert(2);

        Sanctum::actingAs($data['admin']);

        $response = $this->postJson(route('api.litiges.arbitrer', ['id' => $data['litige']->id]), [
            'decision' => 'restitution_beneficiaire',
            'motif' => 'L\'intervenant ne s\'est jamais connecté à la visio.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'litige' => [
                    'statut' => 'resolu',
                    'decision' => 'restitution_beneficiaire',
                ],
            ]);

        // Vérification financière : solde disponible restauré à 5
        $walletBenef = Portefeuille::where('etudiant_id', $data['beneficiaire']->id)->first();
        $this->assertEquals(5, $walletBenef->solde_disponible);
        $this->assertEquals(0, $walletBenef->credits_bloques);

        // RG14 : La réservation associée passe à 'annulee'
        $this->assertEquals('annulee', $data['reservation']->fresh()->statut);
    }

    /**
     * RG14 — Test 9 : Refus de ré-arbitrage d'un litige déjà résolu -> HTTP 422
     */
    public function test_api_arbitrage_refus_litige_deja_resolu(): void
    {
        $data = $this->preparerLitigeOuvert(2);

        Sanctum::actingAs($data['admin']);

        // Premier arbitrage
        $this->postJson(route('api.litiges.arbitrer', ['id' => $data['litige']->id]), [
            'decision' => 'restitution_beneficiaire',
            'motif' => 'Premier jugement',
        ])->assertStatus(200);

        // Deuxième tentative d'arbitrage
        $response = $this->postJson(route('api.litiges.arbitrer', ['id' => $data['litige']->id]), [
            'decision' => 'transfert_intervenant',
            'motif' => 'Deuxième jugement interdit',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'LitigeDejaResoluException',
            ]);
    }
}
