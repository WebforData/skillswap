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
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    protected CreditService $creditService;
    protected ReservationService $reservationService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createTestSchema();

        $this->creditService = new CreditService();
        $this->reservationService = new ReservationService($this->creditService);
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

    private function creerEtudiant(string $email, int $solde = 5, string $statut = 'actif'): User
    {
        $user = new User();
        $user->forceFill([
            'nom' => 'Nom' . uniqid(),
            'prenom' => 'Prenom' . uniqid(),
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'etudiant',
            'statut' => $statut,
        ]);
        $user->save();

        $portefeuille = new Portefeuille();
        $portefeuille->forceFill([
            'etudiant_id' => $user->id,
            'solde_disponible' => $solde,
            'credits_bloques' => 0,
        ]);
        $portefeuille->save();

        return $user;
    }

    private function creerAdmin(string $email): User
    {
        $user = new User();
        $user->forceFill([
            'nom' => 'Admin' . uniqid(),
            'prenom' => 'Super',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'administrateur',
            'statut' => 'actif',
        ]);
        $user->save();

        return $user;
    }

    private function creerOffreEtCreneau(User $intervenant, int $duree = 1, ?Carbon $debut = null): Creneau
    {
        $cat = Categorie::firstOrCreate(['nom' => 'Informatique']);

        $offre = new Offre();
        $offre->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $cat->id,
            'titre' => 'Initiation Laravel REST API',
            'competence' => 'PHP/Laravel',
            'description' => 'Séance d\'initiation API',
            'niveau' => 'debutant',
            'duree' => $duree,
            'mode' => 'presentiel',
            'statut' => 'publiee',
        ]);
        $offre->save();

        $debutSeance = $debut ?? Carbon::now()->addDays(2)->setTime(14, 0);
        $finSeance = (clone $debutSeance)->addHours($duree);

        $creneau = new Creneau();
        $creneau->forceFill([
            'offre_id' => $offre->id,
            'date' => $debutSeance->toDateString(),
            'heure_debut' => $debutSeance->format('H:i:s'),
            'heure_fin' => $finSeance->format('H:i:s'),
            'lieu_public' => 'Salle C103',
            'lien_ou_lieu_prive' => 'https://meet.skillswap.edu/salle-secrete',
        ]);
        $creneau->save();

        return $creneau;
    }

    /**
     * API 1 : Rejet non authentifié HTTP 401
     */
    public function test_api_rejet_non_authentifie(): void
    {
        $response = $this->postJson(route('api.reservations.store'), [
            'creneau_id' => 1,
        ]);

        $response->assertStatus(401);
    }

    /**
     * API 2 : Demande de réservation réussie via POST /api/reservations -> HTTP 201
     */
    public function test_api_demande_reservation_reussie(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('beneficiaire@univ.fr', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant, 2);

        Sanctum::actingAs($beneficiaire);

        $response = $this->postJson(route('api.reservations.store'), [
            'creneau_id' => $creneau->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'reservation' => [
                    'id',
                    'creneau_id',
                    'beneficiaire_id',
                    'statut',
                    'cout_credits',
                    'creneau',
                    'offre',
                ],
            ])
            ->assertJson([
                'reservation' => [
                    'statut' => 'en_attente',
                    'cout_credits' => 2,
                    'beneficiaire_id' => $beneficiaire->id,
                ],
            ]);
    }

    /**
     * API 3 : Auto-réservation interdite via API -> HTTP 422
     */
    public function test_api_auto_reservation_rejetee(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        Sanctum::actingAs($intervenant);

        $response = $this->postJson(route('api.reservations.store'), [
            'creneau_id' => $creneau->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'AutoReservationException',
            ]);
    }

    /**
     * API 4 : Solde insuffisant via API -> HTTP 422
     */
    public function test_api_solde_insuffisant_rejetee(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaireSansSolde = $this->creerEtudiant('pauvre@univ.fr', 0);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1);

        Sanctum::actingAs($beneficiaireSansSolde);

        $response = $this->postJson(route('api.reservations.store'), [
            'creneau_id' => $creneau->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'SoldeInsuffisantPourReservationException',
            ]);
    }

    /**
     * RG19 — Test 1 : Le bénéficiaire peut consulter sa réservation (HTTP 200).
     */
    public function test_api_show_beneficiaire_peut_consulter_sa_reservation(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('beneficiaire@univ.fr', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        Sanctum::actingAs($beneficiaire);

        $response = $this->getJson(route('api.reservations.show', ['id' => $res->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'reservation' => [
                    'id',
                    'creneau_id',
                    'beneficiaire_id',
                    'statut',
                    'cout_credits',
                    'creneau' => ['id', 'date', 'heure_debut', 'heure_fin', 'lieu_public', 'lieu_ou_lien_prive'],
                    'offre',
                    'beneficiaire',
                ],
            ])
            ->assertJson([
                'reservation' => [
                    'id' => $res->id,
                    'beneficiaire_id' => $beneficiaire->id,
                    'statut' => 'acceptee',
                    'creneau' => [
                        'lieu_ou_lien_prive' => 'https://meet.skillswap.edu/salle-secrete',
                    ],
                ],
            ]);
    }

    /**
     * RG19 — Test 2 : L'intervenant peut consulter sa réservation (HTTP 200).
     */
    public function test_api_show_intervenant_peut_consulter_sa_reservation(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('beneficiaire@univ.fr', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        Sanctum::actingAs($intervenant);

        $response = $this->getJson(route('api.reservations.show', ['id' => $res->id]));

        $response->assertStatus(200)
            ->assertJson([
                'reservation' => [
                    'id' => $res->id,
                    'statut' => 'en_attente',
                    'creneau' => [
                        'lieu_ou_lien_prive' => 'https://meet.skillswap.edu/salle-secrete',
                    ],
                ],
            ]);
    }

    /**
     * RG19 — Test 3 : L'administrateur peut consulter la réservation (HTTP 200).
     */
    public function test_api_show_administrateur_peut_consulter_reservation(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('beneficiaire@univ.fr', 4);
        $admin = $this->creerAdmin('admin@univ.fr');
        $creneau = $this->creerOffreEtCreneau($intervenant, 1);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        Sanctum::actingAs($admin);

        $response = $this->getJson(route('api.reservations.show', ['id' => $res->id]));

        $response->assertStatus(200)
            ->assertJson([
                'reservation' => [
                    'id' => $res->id,
                    'statut' => 'en_attente',
                ],
            ]);
    }

    /**
     * RG19 — Test 4 : Un étudiant tiers reçoit HTTP 403.
     */
    public function test_api_show_etudiant_tiers_recoit_403(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('beneficiaire@univ.fr', 4);
        $tiers = $this->creerEtudiant('tiers@univ.fr', 5);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        Sanctum::actingAs($tiers);

        $response = $this->getJson(route('api.reservations.show', ['id' => $res->id]));

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Action non autorisée. Vous n\'avez pas accès à cette réservation (RG19).',
            ]);
    }

    /**
     * RG19 — Test 5 : Un utilisateur non authentifié reçoit HTTP 401.
     */
    public function test_api_show_non_authentifie_recoit_401(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('beneficiaire@univ.fr', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        // Appel direct sans Sanctum::actingAs
        $response = $this->getJson(route('api.reservations.show', ['id' => $res->id]));

        $response->assertStatus(401);
    }

    /**
     * RG19 — Test 6 : Un identifiant inexistant retourne HTTP 404.
     */
    public function test_api_show_identifiant_inexistant_retourne_404(): void
    {
        $etudiant = $this->creerEtudiant('etudiant@univ.fr', 5);

        Sanctum::actingAs($etudiant);

        $response = $this->getJson(route('api.reservations.show', ['id' => 99999]));

        $response->assertStatus(404);
    }

    /**
     * API 6 : Acceptation par l'intervenant -> HTTP 200 et séquestre des crédits
     */
    public function test_api_acceptation_par_intervenant(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('beneficiaire@univ.fr', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant, 2);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        Sanctum::actingAs($intervenant);

        $response = $this->postJson(route('api.reservations.accepter', ['id' => $res->id]));

        $response->assertStatus(200)
            ->assertJson([
                'reservation' => [
                    'statut' => 'acceptee',
                ],
            ]);

        // Vérification financière
        $wallet = Portefeuille::where('etudiant_id', $beneficiaire->id)->first();
        $this->assertEquals(2, $wallet->credits_bloques);
    }

    /**
     * API 7 : Acceptation refusée pour un tiers usurpateur -> HTTP 403
     */
    public function test_api_acceptation_par_tiers_rejetee_403(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('beneficiaire@univ.fr', 4);
        $usurpateur = $this->creerEtudiant('usurpateur@univ.fr', 5);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        Sanctum::actingAs($usurpateur);

        $response = $this->postJson(route('api.reservations.accepter', ['id' => $res->id]));

        $response->assertStatus(403);
    }

    /**
     * API 8 : Refus par l'intervenant avec motif -> HTTP 200
     */
    public function test_api_refus_par_intervenant(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('beneficiaire@univ.fr', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        Sanctum::actingAs($intervenant);

        $response = $this->postJson(route('api.reservations.refuser', ['id' => $res->id]), [
            'motif' => 'Surcharge d\'emploi du temps',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'reservation' => [
                    'statut' => 'refusee',
                    'motif_refus' => 'Surcharge d\'emploi du temps',
                ],
            ]);
    }

    /**
     * API 9 : Retrait par le bénéficiaire -> HTTP 200
     */
    public function test_api_retrait_par_beneficiaire(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('beneficiaire@univ.fr', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        Sanctum::actingAs($beneficiaire);

        $response = $this->postJson(route('api.reservations.retirer', ['id' => $res->id]));

        $response->assertStatus(200)
            ->assertJson([
                'reservation' => [
                    'statut' => 'annulee',
                ],
            ]);
    }

    /**
     * API 10 : Annulation avant début de séance libère crédits -> HTTP 200
     */
    public function test_api_annulation_avant_seance_libere_credits(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('beneficiaire@univ.fr', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        Sanctum::actingAs($beneficiaire);

        $response = $this->postJson(route('api.reservations.annuler', ['id' => $res->id]), [
            'motif' => 'Imprévu familial',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'reservation' => [
                    'statut' => 'annulee',
                ],
            ]);

        $wallet = Portefeuille::where('etudiant_id', $beneficiaire->id)->first();
        $this->assertEquals(4, $wallet->solde_disponible);
        $this->assertEquals(0, $wallet->credits_bloques);
    }

    /**
     * API 11 : Double confirmation et transfert des crédits via API -> HTTP 200
     */
    public function test_api_double_confirmation_et_transfert(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('beneficiaire@univ.fr', 4);

        $debut = Carbon::now()->addHours(1);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1, $debut);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        // Simuler la fin de séance
        Carbon::setTestNow(Carbon::now()->addHours(3));

        try {
            // Confirmation 1 : bénéficiaire
            Sanctum::actingAs($beneficiaire);
            $resp1 = $this->postJson(route('api.reservations.confirmer', ['id' => $res->id]));
            $resp1->assertStatus(200)
                ->assertJson([
                    'reservation' => [
                        'statut' => 'acceptee',
                        'confirmation_beneficiaire' => true,
                    ],
                ]);

            // Confirmation 2 : intervenant -> statut bascule à realisee
            Sanctum::actingAs($intervenant);
            $resp2 = $this->postJson(route('api.reservations.confirmer', ['id' => $res->id]));
            $resp2->assertStatus(200)
                ->assertJson([
                    'reservation' => [
                        'statut' => 'realisee',
                        'confirmation_intervenant' => true,
                    ],
                ]);

            // Vérifier solde intervenant crédité
            $walletInter = Portefeuille::where('etudiant_id', $intervenant->id)->first();
            $this->assertEquals(6, $walletInter->solde_disponible);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * API 12 : Déclaration de litige après début de séance -> HTTP 201
     */
    public function test_api_declaration_litige(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('beneficiaire@univ.fr', 4);

        $debut = Carbon::now()->addHours(1);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1, $debut);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        Carbon::setTestNow(Carbon::now()->addHours(2));

        try {
            Sanctum::actingAs($beneficiaire);

            $response = $this->postJson(route('api.reservations.litige', ['id' => $res->id]), [
                'description' => 'L\'intervenant s\'est déconnecté au bout de 5 minutes sans explication.',
            ]);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'litige' => [
                        'id',
                        'reservation_id',
                        'declarant_id',
                        'statut',
                        'description',
                    ],
                ])
                ->assertJson([
                    'litige' => [
                        'statut' => 'ouvert',
                        'declarant_id' => $beneficiaire->id,
                    ],
                ]);

            $resFresh = Reservation::find($res->id);
            $this->assertEquals('en_litige', $resFresh->statut);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * API 13 : Compte désactivé rejeté avec HTTP 403 (RG18)
     */
    public function test_api_compte_desactive_rejet_403(): void
    {
        $intervenant = $this->creerEtudiant('intervenant@univ.fr', 5);
        $beneficiaireBloque = $this->creerEtudiant('bloque@univ.fr', 5, 'desactive');
        $creneau = $this->creerOffreEtCreneau($intervenant);

        Sanctum::actingAs($beneficiaireBloque);

        $response = $this->postJson(route('api.reservations.store'), [
            'creneau_id' => $creneau->id,
        ]);

        $response->assertStatus(403);
    }

    /**
     * API 14 : Consultation de la liste des réservations avec pagination et filtres (RG19)
     */
    public function test_api_index_reservations_liste_authentifiee_avec_filtres(): void
    {
        $intervenant = $this->creerEtudiant('interv_index@univ.fr', 5);
        $beneficiaire = $this->creerEtudiant('benef_index@univ.fr', 5);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        Sanctum::actingAs($beneficiaire);

        $response = $this->getJson(route('api.reservations.index'));

        $response->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('reservations.0.id', $res->id)
            ->assertJsonPath('reservations.0.beneficiaire_id', $beneficiaire->id);

        // Test filtre par rôle intervenant (ne doit rien retourner pour le bénéficiaire)
        $respRole = $this->getJson(route('api.reservations.index', ['role' => 'intervenant']));
        $respRole->assertStatus(200)
            ->assertJsonPath('total', 0);
    }

    /**
     * API 15 : Rejet non authentifié sur la consultation de la liste (HTTP 401)
     */
    public function test_api_index_non_authentifie_recoit_401(): void
    {
        $response = $this->getJson(route('api.reservations.index'));
        $response->assertStatus(401);
    }

    /**
     * API 16 : Rejet strict d'une double acceptation sur le même créneau (RG07)
     */
    public function test_api_double_acceptation_rejetee(): void
    {
        $intervenant = $this->creerEtudiant('interv_double_api@univ.fr', 5);
        $eleve1 = $this->creerEtudiant('eleve1_api@univ.fr', 5);
        $eleve2 = $this->creerEtudiant('eleve2_api@univ.fr', 5);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $res1 = $this->reservationService->demanderReservation($eleve1, $creneau->id);
        $res2 = $this->reservationService->demanderReservation($eleve2, $creneau->id);

        Sanctum::actingAs($intervenant);

        // 1ère acceptation -> 200 OK
        $resp1 = $this->postJson(route('api.reservations.accepter', ['id' => $res1->id]));
        $resp1->assertStatus(200);

        // 2ème acceptation -> rejetée
        $resp2 = $this->postJson(route('api.reservations.accepter', ['id' => $res2->id]));
        $resp2->assertStatus(422); // StatutReservationInvalideException (la réservation est déjà passée à 'refusee')
    }
}
