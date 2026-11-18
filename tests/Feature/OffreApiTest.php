<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\Offre;
use App\Models\Portefeuille;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CreditService;
use App\Services\OffreService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OffreApiTest extends TestCase
{
    protected CreditService $creditService;
    protected ReservationService $reservationService;
    protected OffreService $offreService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createTestSchema();

        $this->creditService = new CreditService();
        $this->reservationService = new ReservationService($this->creditService);
        $this->offreService = new OffreService();
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

    /**
     * Test 1 : Création d'une offre via l'API (HTTP 201)
     */
    public function test_api_creation_offre_reussie(): void
    {
        $intervenant = $this->creerUtilisateur('formateur@univ.fr');
        $categorie = Categorie::create(['nom' => 'Algorithmique']);

        Sanctum::actingAs($intervenant);

        $response = $this->postJson(route('api.offres.store'), [
            'categorie_id' => $categorie->id,
            'titre' => 'Structures de Données & Arbres Binaires',
            'competence' => 'C / C++',
            'description' => 'Parcours d arbres, tas binaires et complexité temporelle.',
            'niveau' => 'intermediaire',
            'duree' => 2,
            'mode' => 'presentiel',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'offre' => [
                    'id',
                    'etudiant_id',
                    'categorie_id',
                    'titre',
                    'competence',
                    'description',
                    'niveau',
                    'duree',
                    'mode',
                    'statut',
                ],
            ])
            ->assertJson([
                'offre' => [
                    'etudiant_id' => $intervenant->id,
                    'titre' => 'Structures de Données & Arbres Binaires',
                    'duree' => 2,
                    'statut' => 'brouillon',
                ],
            ]);
    }

    /**
     * Test 2 : Rejet de la création si non authentifié -> HTTP 401
     */
    public function test_api_creation_offre_rejet_non_authentifie(): void
    {
        $categorie = Categorie::create(['nom' => 'Physique']);

        $response = $this->postJson(route('api.offres.store'), [
            'categorie_id' => $categorie->id,
            'titre' => 'Physique Quantique',
            'competence' => 'Physique',
            'description' => 'Cours d introduction aux opérateurs.',
            'niveau' => 'debutant',
            'duree' => 1,
            'mode' => 'distanciel',
        ]);

        $response->assertStatus(401);
    }

    /**
     * RG02 — Test 3 : Rejet de création si durée invalide (hors 1, 2) -> HTTP 422
     */
    public function test_api_creation_offre_rejet_duree_invalide(): void
    {
        $intervenant = $this->creerUtilisateur('duree_invalide@univ.fr');
        $categorie = Categorie::create(['nom' => 'Chimie']);

        Sanctum::actingAs($intervenant);

        $response = $this->postJson(route('api.offres.store'), [
            'categorie_id' => $categorie->id,
            'titre' => 'Séance Trop Longue Invalide',
            'competence' => 'Chimie',
            'description' => 'Description détaillée de la séance de chimie.',
            'niveau' => 'debutant',
            'duree' => 3, // Invalide : strictement 1 ou 2 (RG02)
            'mode' => 'distanciel',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['duree']);
    }

    /**
     * Test 4 : Modification d'une offre par son propriétaire -> HTTP 200
     */
    public function test_api_modification_offre_par_proprietaire(): void
    {
        $intervenant = $this->creerUtilisateur('editeur@univ.fr');
        $categorie = Categorie::create(['nom' => 'Droit']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Ancien Titre Juridique',
            'competence' => 'Droit Privé',
            'description' => 'Description initiale d au moins 20 caractères.',
            'duree' => 1,
        ]);

        Sanctum::actingAs($intervenant);

        $response = $this->putJson(route('api.offres.update', ['id' => $offre->id]), [
            'titre' => 'Titre Juridique Révisé et Amélioré',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'offre' => [
                    'id' => $offre->id,
                    'titre' => 'Titre Juridique Révisé et Amélioré',
                ],
            ]);
    }

    /**
     * Test 5 : Rejet de modification par un tiers -> HTTP 403
     */
    public function test_api_modification_offre_rejet_par_tiers(): void
    {
        $intervenant = $this->creerUtilisateur('proprietaire@univ.fr');
        $tiers = $this->creerUtilisateur('usurpateur@univ.fr');
        $categorie = Categorie::create(['nom' => 'Biologie']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Biologie Cellulaire',
            'competence' => 'SVT',
            'description' => 'Étude de la mitose et méiose.',
        ]);

        Sanctum::actingAs($tiers);

        $response = $this->putJson(route('api.offres.update', ['id' => $offre->id]), [
            'titre' => 'Modification Frauduleuse',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'OffreNonAutoriseeException',
            ]);
    }

    /**
     * Test 6 : Publication d'une offre -> HTTP 200
     */
    public function test_api_publication_offre(): void
    {
        $intervenant = $this->creerUtilisateur('pub_api@univ.fr');
        $categorie = Categorie::create(['nom' => 'Économie']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Macroéconomie & PIB',
            'competence' => 'Économie',
            'description' => 'Analyse des politiques monétaires.',
        ]);

        Sanctum::actingAs($intervenant);

        $response = $this->postJson(route('api.offres.publier', ['id' => $offre->id]));

        $response->assertStatus(200)
            ->assertJson([
                'offre' => [
                    'id' => $offre->id,
                    'statut' => 'publiee',
                ],
            ]);
    }

    /**
     * RG17 — Test 7 : Archivage d'une offre et annulation des réservations en attente
     */
    public function test_api_archivage_offre_rg17(): void
    {
        $intervenant = $this->creerUtilisateur('prof_rg17@univ.fr');
        $beneficiaire = $this->creerUtilisateur('eleve_rg17@univ.fr');
        $categorie = Categorie::create(['nom' => 'Statistiques']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Probabilités Discrètes',
            'competence' => 'Stats',
            'description' => 'Lois binomiales, de Poisson et espérances.',
            'statut' => 'publiee',
        ]);

        $creneau = new Creneau();
        $creneau->forceFill([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(5)->toDateString(),
            'heure_debut' => '10:00:00',
            'heure_fin' => '11:00:00',
            'lieu_public' => 'Amphi C',
            'lien_ou_lieu_prive' => 'Bâtiment 2',
        ]);
        $creneau->save();

        $reservation = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->assertEquals('en_attente', $reservation->fresh()->statut);

        Sanctum::actingAs($intervenant);

        $response = $this->postJson(route('api.offres.archiver', ['id' => $offre->id]));

        $response->assertStatus(200)
            ->assertJson([
                'offre' => [
                    'id' => $offre->id,
                    'statut' => 'archivee',
                ],
            ]);

        // Vérification de l'annulation de la demande en attente (RG17)
        $this->assertEquals('annulee', $reservation->fresh()->statut);
    }

    /**
     * RG04 — Test 8 : Catalogue public accessible sans authentification
     */
    public function test_api_recherche_catalogue_public(): void
    {
        $intervenant = $this->creerUtilisateur('public_prof@univ.fr');
        $categorie = Categorie::create(['nom' => 'Histoire']);

        $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Histoire Contemporaine Mondiale',
            'competence' => 'Histoire',
            'description' => 'Les relations internationales au XXème siècle.',
            'statut' => 'publiee',
        ]);

        // Appel sans aucun token d'authentification
        $response = $this->getJson(route('api.offres.index', ['q' => 'Histoire']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'titre',
                        'competence',
                        'categorie',
                        'intervenant',
                    ],
                ],
            ]);
    }

    /**
     * RG19 — Test 9 : Les détails privés des créneaux ne sont pas exposés dans la consultation publique
     */
    public function test_api_absence_exposition_details_prives_creneaux(): void
    {
        $intervenant = $this->creerUtilisateur('securite_offre@univ.fr');
        $categorie = Categorie::create(['nom' => 'Sécurité Informatique']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Cybersécurité & Pentest',
            'competence' => 'Sécurité',
            'description' => 'Audit de vulnérabilités et injection SQL.',
            'statut' => 'publiee',
        ]);

        $creneau = new Creneau();
        $creneau->forceFill([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(5)->toDateString(),
            'heure_debut' => '14:00:00',
            'heure_fin' => '15:00:00',
            'lieu_public' => 'En ligne',
            'lien_ou_lieu_prive' => 'https://meet.skillswap.edu/salle-secrete-confidentielle',
        ]);
        $creneau->save();

        $response = $this->getJson(route('api.offres.show', ['id' => $offre->id]));

        $response->assertStatus(200)
            ->assertJsonMissing([
                'lien_ou_lieu_prive' => 'https://meet.skillswap.edu/salle-secrete-confidentielle',
            ]);
    }
}
