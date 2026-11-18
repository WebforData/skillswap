<?php

namespace Tests\Unit;

use App\Exceptions\CompteDesactiveException;
use App\Exceptions\DoubleEvaluationException;
use App\Exceptions\EvaluationNonAutoriseeException;
use App\Exceptions\NoteInvalideException;
use App\Exceptions\ReservationNonEvalueeException;
use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\Evaluation;
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
use Tests\TestCase;

class EvaluationServiceTest extends TestCase
{
    private EvaluationService $evaluationService;
    private ReservationService $reservationService;
    private CreditService $creditService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createSchema();
        $this->creditService = new CreditService();
        $this->reservationService = new ReservationService($this->creditService);
        $this->evaluationService = new EvaluationService();
    }

    private function createSchema(): void
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

    private function preparerSeanceRealisee(int $duree = 1): array
    {
        $intervenant = $this->creerUtilisateur('intervenant_' . uniqid() . '@univ.fr');
        $beneficiaire = $this->creerUtilisateur('beneficiaire_' . uniqid() . '@univ.fr');

        $categorie = Categorie::create(['nom' => 'Programmation ' . uniqid()]);

        $offre = new Offre();
        $offre->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Initiation Laravel 12',
            'competence' => 'PHP/Laravel',
            'description' => 'Séance complète d apprentissage',
            'niveau' => 'debutant',
            'duree' => $duree,
            'mode' => 'distanciel',
            'statut' => 'publiee',
        ]);
        $offre->save();

        $debut = Carbon::now()->addDays(2)->setTime(10, 0, 0);
        $fin = (clone $debut)->addHours($duree);

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

        // 1. Demande & Acceptation
        $reservation = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $reservation->id);

        // 2. Double confirmation après la fin de séance (RG11)
        Carbon::setTestNow((clone $fin)->addMinutes(10));
        $this->reservationService->confirmerRealisation($intervenant, $reservation->id);
        $this->reservationService->confirmerRealisation($beneficiaire, $reservation->id);
        Carbon::setTestNow();

        $reservation->refresh();

        return compact('intervenant', 'beneficiaire', 'reservation', 'creneau', 'offre');
    }

    /**
     * RG15 — Test 1 : Le bénéficiaire évalue l'intervenant pour une séance réalisée
     */
    public function test_beneficiaire_peut_evaluer_intervenant_pour_seance_realisee(): void
    {
        $data = $this->preparerSeanceRealisee();

        $evaluation = $this->evaluationService->evaluerReservation(
            $data['beneficiaire'],
            $data['reservation']->id,
            5,
            'Excellente pédagogie et séance très claire !'
        );

        $this->assertEquals('realisee', $data['reservation']->statut);
        $this->assertEquals($data['beneficiaire']->id, $evaluation->auteur_id);
        $this->assertEquals($data['intervenant']->id, $evaluation->cible_id);
        $this->assertEquals(5, $evaluation->note);
        $this->assertEquals('Excellente pédagogie et séance très claire !', $evaluation->commentaire);
    }

    /**
     * RG15 — Test 2 : L'intervenant évalue le bénéficiaire pour une séance réalisée
     */
    public function test_intervenant_peut_evaluer_beneficiaire_pour_seance_realisee(): void
    {
        $data = $this->preparerSeanceRealisee();

        $evaluation = $this->evaluationService->evaluerReservation(
            $data['intervenant'],
            $data['reservation']->id,
            4,
            'Étudiant motivé et attentif.'
        );

        $this->assertEquals($data['intervenant']->id, $evaluation->auteur_id);
        $this->assertEquals($data['beneficiaire']->id, $evaluation->cible_id);
        $this->assertEquals(4, $evaluation->note);
    }

    /**
     * RG15 — Test 3 : Interdiction de double évaluation par le même participant
     */
    public function test_interdiction_double_evaluation_par_le_meme_participant(): void
    {
        $data = $this->preparerSeanceRealisee();

        // Première évaluation
        $this->evaluationService->evaluerReservation(
            $data['beneficiaire'],
            $data['reservation']->id,
            5,
            'Premier avis'
        );

        // Seconde évaluation
        $this->expectException(DoubleEvaluationException::class);
        $this->evaluationService->evaluerReservation(
            $data['beneficiaire'],
            $data['reservation']->id,
            4,
            'Deuxième avis interdit'
        );
    }

    /**
     * RG15 — Test 4 : Les deux participants peuvent chacun déposer une évaluation
     */
    public function test_les_deux_participants_peuvent_evaluer_la_meme_seance(): void
    {
        $data = $this->preparerSeanceRealisee();

        $evalBenef = $this->evaluationService->evaluerReservation(
            $data['beneficiaire'],
            $data['reservation']->id,
            5,
            'Avis du bénéficiaire'
        );

        $evalInter = $this->evaluationService->evaluerReservation(
            $data['intervenant'],
            $data['reservation']->id,
            4,
            'Avis de l intervenant'
        );

        $this->assertNotEquals($evalBenef->id, $evalInter->id);
        $this->assertEquals(2, Evaluation::where('reservation_id', $data['reservation']->id)->count());
    }

    /**
     * RG15 — Test 5 : Interdiction d'évaluer une réservation non réalisée (ex: acceptée, en attente, annulée)
     */
    public function test_interdiction_evaluer_reservation_non_realisee(): void
    {
        $data = $this->preparerSeanceRealisee();

        // Forcer le statut à 'acceptee'
        $data['reservation']->statut = 'acceptee';
        $data['reservation']->save();

        $this->expectException(ReservationNonEvalueeException::class);
        $this->evaluationService->evaluerReservation(
            $data['beneficiaire'],
            $data['reservation']->id,
            5,
            'Trop tôt'
        );
    }

    /**
     * RG15 — Test 6 : Rejet si l'auteur n'est pas participant
     */
    public function test_rejet_si_utilisateur_tiers_non_participant(): void
    {
        $data = $this->preparerSeanceRealisee();
        $tiers = $this->creerUtilisateur('tiers_' . uniqid() . '@univ.fr');

        $this->expectException(EvaluationNonAutoriseeException::class);
        $this->evaluationService->evaluerReservation(
            $tiers,
            $data['reservation']->id,
            5,
            'Tentative frauduleuse'
        );
    }

    /**
     * RG15 — Test 7 : Rejet si note hors bornes 1..5
     */
    public function test_rejet_si_note_invalide(): void
    {
        $data = $this->preparerSeanceRealisee();

        $this->expectException(NoteInvalideException::class);
        $this->evaluationService->evaluerReservation(
            $data['beneficiaire'],
            $data['reservation']->id,
            6, // Hors limite
            'Note invalide'
        );
    }

    /**
     * RG18 — Test 8 : Rejet si le compte est désactivé
     */
    public function test_rejet_si_compte_auteur_desactive(): void
    {
        $data = $this->preparerSeanceRealisee();
        $data['beneficiaire']->statut = 'desactive';
        $data['beneficiaire']->save();

        $this->expectException(CompteDesactiveException::class);
        $this->evaluationService->evaluerReservation(
            $data['beneficiaire'],
            $data['reservation']->id,
            5,
            'Compte bloqué'
        );
    }

    /**
     * RG15 — Test 9 : Calcul des statistiques et note moyenne
     */
    public function test_statistiques_et_note_moyenne(): void
    {
        $data1 = $this->preparerSeanceRealisee();
        $data2 = $this->preparerSeanceRealisee();

        // Les deux bénéficiaires évaluent le même intervenant dans 2 séances distinctes
        $this->evaluationService->evaluerReservation($data1['beneficiaire'], $data1['reservation']->id, 5, 'Super');
        
        // Relier l'offre de data2 au même intervenant
        $data2['offre']->etudiant_id = $data1['intervenant']->id;
        $data2['offre']->save();
        $data2['reservation']->refresh();

        $this->evaluationService->evaluerReservation($data2['beneficiaire'], $data2['reservation']->id, 3, 'Moyen');

        $stats = $this->evaluationService->getStatistiquesEtudiant($data1['intervenant']->id);

        $this->assertEquals(2, $stats['nombre_evaluations']);
        $this->assertEquals(4.0, $stats['note_moyenne']); // (5 + 3) / 2 = 4.0
    }
}
