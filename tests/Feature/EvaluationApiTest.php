<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\Evaluation;
use App\Models\Litige;
use App\Models\MouvementCredit;
use App\Models\Offre;
use App\Models\Portefeuille;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CreditService;
use App\Services\EvaluationService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EvaluationApiTest extends TestCase
{
    protected CreditService $creditService;
    protected ReservationService $reservationService;
    protected EvaluationService $evaluationService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createTestSchema();

        $this->creditService = new CreditService();
        $this->reservationService = new ReservationService($this->creditService);
        $this->evaluationService = new EvaluationService();
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

        Schema::create('litiges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations');
            $table->foreignId('declarant_id')->constrained('users');
            $table->foreignId('admin_id')->nullable()->constrained('users');
            $table->string('statut')->default('ouvert');
            $table->string('decision')->nullable();
            $table->text('motif_decision')->nullable();
            $table->dateTime('date_resolution')->nullable();
            $table->text('description');
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
            $table->unique(['reservation_id', 'auteur_id'], 'uk_evaluation_unique_par_auteur');
        });
    }

    private function creerUtilisateur(string $email, string $role = 'etudiant', string $statut = 'actif', int $solde = 5): User
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
            'solde_disponible' => $solde,
            'credits_bloques' => 0,
        ]);
        $portefeuille->save();

        return $user;
    }

    private function preparerSeanceRealisee(): array
    {
        $intervenant = $this->creerUtilisateur('intervenant_' . uniqid() . '@univ.fr');
        $beneficiaire = $this->creerUtilisateur('beneficiaire_' . uniqid() . '@univ.fr');

        $categorie = Categorie::create(['nom' => 'Catégorie ' . uniqid()]);

        $offre = new Offre();
        $offre->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Formation React & TypeScript',
            'competence' => 'Frontend',
            'description' => 'Maîtriser les composants et hooks',
            'niveau' => 'intermediaire',
            'duree' => 1,
            'mode' => 'distanciel',
            'statut' => 'publiee',
        ]);
        $offre->save();

        $debut = Carbon::now()->addDays(3)->setTime(14, 0, 0);
        $fin = (clone $debut)->addHours(1);

        $creneau = new Creneau();
        $creneau->forceFill([
            'offre_id' => $offre->id,
            'date' => $debut->toDateString(),
            'heure_debut' => $debut->format('H:i:s'),
            'heure_fin' => $fin->format('H:i:s'),
            'lieu_public' => 'En ligne',
            'lien_ou_lieu_prive' => 'https://meet.skillswap.edu/salle-react',
        ]);
        $creneau->save();

        $reservation = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $reservation->id);

        // Clôturer la séance en double confirmation
        Carbon::setTestNow((clone $fin)->addMinutes(5));
        $this->reservationService->confirmerRealisation($intervenant, $reservation->id);
        $this->reservationService->confirmerRealisation($beneficiaire, $reservation->id);
        Carbon::setTestNow();

        $reservation->refresh();

        return compact('intervenant', 'beneficiaire', 'reservation');
    }

    /**
     * Test 1 : Rejet d'un utilisateur non authentifié -> HTTP 401
     */
    public function test_api_evaluation_rejet_non_authentifie(): void
    {
        $response = $this->postJson(route('api.evaluations.store'), [
            'reservation_id' => 1,
            'note' => 5,
            'commentaire' => 'Super',
        ]);

        $response->assertStatus(401);
    }

    /**
     * RG15 — Test 2 : Évaluation réussie par le bénéficiaire -> HTTP 201
     */
    public function test_api_evaluation_reussi_par_beneficiaire(): void
    {
        $data = $this->preparerSeanceRealisee();

        Sanctum::actingAs($data['beneficiaire']);

        $response = $this->postJson(route('api.evaluations.store'), [
            'reservation_id' => $data['reservation']->id,
            'note' => 5,
            'commentaire' => 'Excellente séance, formateur très pédagogue !',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'evaluation' => [
                    'id',
                    'reservation_id',
                    'auteur_id',
                    'cible_id',
                    'note',
                    'commentaire',
                    'auteur',
                    'cible',
                ],
            ])
            ->assertJson([
                'evaluation' => [
                    'reservation_id' => $data['reservation']->id,
                    'auteur_id' => $data['beneficiaire']->id,
                    'cible_id' => $data['intervenant']->id,
                    'note' => 5,
                ],
            ]);
    }

    /**
     * RG15 — Test 3 : Évaluation réussie par l'intervenant -> HTTP 201
     */
    public function test_api_evaluation_reussi_par_intervenant(): void
    {
        $data = $this->preparerSeanceRealisee();

        Sanctum::actingAs($data['intervenant']);

        $response = $this->postJson(route('api.evaluations.store'), [
            'reservation_id' => $data['reservation']->id,
            'note' => 4,
            'commentaire' => 'Bon échange, élève curieux.',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'evaluation' => [
                    'auteur_id' => $data['intervenant']->id,
                    'cible_id' => $data['beneficiaire']->id,
                    'note' => 4,
                ],
            ]);
    }

    /**
     * RG15 — Test 4 : Rejet de la double évaluation par le même auteur -> HTTP 422
     */
    public function test_api_evaluation_rejet_double_soumission(): void
    {
        $data = $this->preparerSeanceRealisee();

        Sanctum::actingAs($data['beneficiaire']);

        // Première évaluation
        $this->postJson(route('api.evaluations.store'), [
            'reservation_id' => $data['reservation']->id,
            'note' => 5,
            'commentaire' => 'Premier avis',
        ])->assertStatus(201);

        // Seconde évaluation
        $response = $this->postJson(route('api.evaluations.store'), [
            'reservation_id' => $data['reservation']->id,
            'note' => 4,
            'commentaire' => 'Deuxième avis frauduleux',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'DoubleEvaluationException',
            ]);
    }

    /**
     * RG15 — Test 5 : Rejet si l'étudiant n'est pas participant à la séance -> HTTP 403
     */
    public function test_api_evaluation_rejet_tiers_non_participant(): void
    {
        $data = $this->preparerSeanceRealisee();
        $tiers = $this->creerUtilisateur('tiers_' . uniqid() . '@univ.fr');

        Sanctum::actingAs($tiers);

        $response = $this->postJson(route('api.evaluations.store'), [
            'reservation_id' => $data['reservation']->id,
            'note' => 5,
            'commentaire' => 'Je n y étais pas mais je note',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'EvaluationNonAutoriseeException',
            ]);
    }

    /**
     * RG15 — Test 6 : Rejet si la réservation n'est pas encore réalisée -> HTTP 422
     */
    public function test_api_evaluation_rejet_reservation_non_realisee(): void
    {
        $data = $this->preparerSeanceRealisee();
        $data['reservation']->statut = 'acceptee';
        $data['reservation']->save();

        Sanctum::actingAs($data['beneficiaire']);

        $response = $this->postJson(route('api.evaluations.store'), [
            'reservation_id' => $data['reservation']->id,
            'note' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'ReservationNonEvalueeException',
            ]);
    }

    /**
     * RG15 — Test 7 : Rejet si note hors limites (ex: 0 ou 6) -> HTTP 422
     */
    public function test_api_evaluation_rejet_note_hors_limites(): void
    {
        $data = $this->preparerSeanceRealisee();

        Sanctum::actingAs($data['beneficiaire']);

        $response = $this->postJson(route('api.evaluations.store'), [
            'reservation_id' => $data['reservation']->id,
            'note' => 6,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['note']);
    }

    /**
     * RG18 — Test 8 : Rejet si le compte est désactivé -> HTTP 403
     */
    public function test_api_evaluation_rejet_compte_desactive(): void
    {
        $data = $this->preparerSeanceRealisee();
        $data['beneficiaire']->statut = 'desactive';
        $data['beneficiaire']->save();

        Sanctum::actingAs($data['beneficiaire']);

        $response = $this->postJson(route('api.evaluations.store'), [
            'reservation_id' => $data['reservation']->id,
            'note' => 5,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'CompteDesactiveException',
            ]);
    }

    /**
     * Test 9 : Consultation des évaluations reçues et données
     */
    public function test_api_consultation_evaluations_recues_et_donnees(): void
    {
        $data = $this->preparerSeanceRealisee();

        $this->evaluationService->evaluerReservation($data['beneficiaire'], $data['reservation']->id, 5, 'Super');

        // L'intervenant consulte ses évaluations reçues
        Sanctum::actingAs($data['intervenant']);
        $respRecues = $this->getJson(route('api.evaluations.recues'));
        $respRecues->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.note', 5);

        // Le bénéficiaire consulte ses évaluations données
        Sanctum::actingAs($data['beneficiaire']);
        $respDonnees = $this->getJson(route('api.evaluations.donnees'));
        $respDonnees->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.note', 5);
    }

    /**
     * Test 10 : Consultation des statistiques d'un étudiant
     */
    public function test_api_statistiques_etudiant(): void
    {
        $data = $this->preparerSeanceRealisee();

        $this->evaluationService->evaluerReservation($data['beneficiaire'], $data['reservation']->id, 4, 'Bien');

        $response = $this->getJson(route('api.users.evaluations.stats', ['id' => $data['intervenant']->id]));

        $response->assertStatus(200)
            ->assertJson([
                'etudiant_id' => $data['intervenant']->id,
                'nombre_evaluations' => 1,
                'note_moyenne' => 4.0,
            ]);
    }
}
