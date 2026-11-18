<?php

namespace Tests\Unit;

use App\Exceptions\AccesReservationNonAutoriseException;
use App\Exceptions\CompteDesactiveException;
use App\Exceptions\DecisionLitigeInvalideException;
use App\Exceptions\LitigeDejaResoluException;
use App\Exceptions\ReservationNonLitigieuseException;
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
use Tests\TestCase;

class LitigeServiceTest extends TestCase
{
    private LitigeService $litigeService;
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
        $this->litigeService = new LitigeService($this->creditService);
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
            'titre' => 'Formation Python Avancé',
            'competence' => 'Python',
            'description' => 'Séance avancée',
            'niveau' => 'avance',
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
            'lien_ou_lieu_prive' => 'https://meet.skillswap.edu/salle-litige',
        ]);
        $creneau->save();

        // 1. Demande
        $reservation = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        // 2. Acceptation -> crédits bloqués
        $this->reservationService->accepterReservation($intervenant, $reservation->id);

        // 3. Début de séance et ouverture de litige
        Carbon::setTestNow(Carbon::now()->addHours(2));
        $litige = $this->reservationService->ouvrirLitige($beneficiaire, $reservation->id, "L'intervenant n'a pas assuré la séance.");
        Carbon::setTestNow();

        return compact('intervenant', 'beneficiaire', 'admin', 'reservation', 'litige');
    }

    /**
     * RG13 — Test 1 : Arbitrage favorable à l'intervenant (transfert des crédits)
     */
    public function test_arbitrage_favorable_intervenant_transfere_credits_et_resout_litige(): void
    {
        $data = $this->preparerLitigeOuvert(2);

        $litigeResolu = $this->litigeService->arbitrerLitige(
            $data['admin'],
            $data['litige']->id,
            'transfert_intervenant',
            'Preuve de présence confirmée pour l\'intervenant'
        );

        // Vérification du statut du litige
        $this->assertEquals('resolu', $litigeResolu->statut);
        $this->assertEquals('transfert_intervenant', $litigeResolu->decision);
        $this->assertEquals($data['admin']->id, $litigeResolu->admin_id);
        $this->assertNotNull($litigeResolu->date_resolution);

        // RG14 : La réservation associée doit être passée à 'realisee'
        $reservationFresh = Reservation::find($data['reservation']->id);
        $this->assertEquals('realisee', $reservationFresh->statut);
        $this->assertTrue((bool) $reservationFresh->confirmation_intervenant);
        $this->assertTrue((bool) $reservationFresh->confirmation_beneficiaire);

        // Portefeuille bénéficiaire : crédits bloqués débités
        $walletBenef = Portefeuille::where('etudiant_id', $data['beneficiaire']->id)->first();
        $this->assertEquals(0, $walletBenef->credits_bloques);
        $this->assertEquals(3, $walletBenef->solde_disponible); // 5 - 2 = 3

        // Portefeuille intervenant : crédits versés au disponible
        $walletInter = Portefeuille::where('etudiant_id', $data['intervenant']->id)->first();
        $this->assertEquals(7, $walletInter->solde_disponible); // 5 + 2 = 7

        // Vérification des mouvements financiers
        $mvts = MouvementCredit::where('reservation_id', $data['reservation']->id)
            ->where('type', 'transfert_gain')
            ->get();

        $this->assertCount(2, $mvts);
        $this->assertEquals(-2, $mvts->where('portefeuille_id', $walletBenef->id)->first()->quantite);
        $this->assertEquals(2, $mvts->where('portefeuille_id', $walletInter->id)->first()->quantite);
    }

    /**
     * RG13 — Test 2 : Arbitrage favorable au bénéficiaire (restitution des crédits)
     */
    public function test_arbitrage_favorable_beneficiaire_restitue_credits_et_resout_litige(): void
    {
        $data = $this->preparerLitigeOuvert(2);

        $litigeResolu = $this->litigeService->arbitrerLitige(
            $data['admin'],
            $data['litige']->id,
            'restitution_beneficiaire',
            'Absence constatée de l\'intervenant'
        );

        $this->assertEquals('resolu', $litigeResolu->statut);
        $this->assertEquals('restitution_beneficiaire', $litigeResolu->decision);

        // RG14 : La réservation associée doit être passée à 'annulee'
        $reservationFresh = Reservation::find($data['reservation']->id);
        $this->assertEquals('annulee', $reservationFresh->statut);

        // Portefeuille bénéficiaire : crédits restitués au solde disponible
        $walletBenef = Portefeuille::where('etudiant_id', $data['beneficiaire']->id)->first();
        $this->assertEquals(0, $walletBenef->credits_bloques);
        $this->assertEquals(5, $walletBenef->solde_disponible); // Restitué à 5

        // Portefeuille intervenant : inchangé
        $walletInter = Portefeuille::where('etudiant_id', $data['intervenant']->id)->first();
        $this->assertEquals(5, $walletInter->solde_disponible);

        // Vérification du mouvement de restitution
        $mvtRestitution = MouvementCredit::where('reservation_id', $data['reservation']->id)
            ->where('type', 'restitution_litige')
            ->first();

        $this->assertNotNull($mvtRestitution);
        $this->assertEquals(2, $mvtRestitution->quantite);
    }

    /**
     * Test 3 : Refus si l'utilisateur n'est pas un administrateur
     */
    public function test_arbitrage_refuse_si_utilisateur_non_administrateur(): void
    {
        $data = $this->preparerLitigeOuvert();
        $etudiantTiers = $this->creerUtilisateur('tiers@univ.fr', 'etudiant', 'actif');

        $this->expectException(AccesReservationNonAutoriseException::class);
        $this->litigeService->arbitrerLitige($etudiantTiers, $data['litige']->id, 'restitution_beneficiaire', 'Motif test');
    }

    /**
     * Test 4 : Refus si l'administrateur est désactivé (RG18)
     */
    public function test_arbitrage_refuse_si_administrateur_desactive(): void
    {
        $data = $this->preparerLitigeOuvert();
        $adminBloque = $this->creerUtilisateur('admin_bloque@univ.fr', 'administrateur', 'desactive');

        $this->expectException(CompteDesactiveException::class);
        $this->litigeService->arbitrerLitige($adminBloque, $data['litige']->id, 'restitution_beneficiaire', 'Motif test');
    }

    /**
     * RG14 — Test 5 : Refus si le litige est déjà résolu (aucun double arbitrage)
     */
    public function test_arbitrage_refuse_si_litige_deja_resolu(): void
    {
        $data = $this->preparerLitigeOuvert();

        // Premier arbitrage
        $this->litigeService->arbitrerLitige(
            $data['admin'],
            $data['litige']->id,
            'restitution_beneficiaire',
            'Premier arbitrage'
        );

        // Deuxième arbitrage sur le même dossier
        $this->expectException(LitigeDejaResoluException::class);
        $this->litigeService->arbitrerLitige(
            $data['admin'],
            $data['litige']->id,
            'transfert_intervenant',
            'Tentative de révision'
        );
    }

    /**
     * Test 6 : Refus si la réservation n'est pas en litige
     */
    public function test_arbitrage_refuse_si_reservation_non_en_litige(): void
    {
        $data = $this->preparerLitigeOuvert();

        // Forcer le statut de la réservation à 'acceptee'
        $data['reservation']->statut = 'acceptee';
        $data['reservation']->save();

        $this->expectException(ReservationNonLitigieuseException::class);
        $this->litigeService->arbitrerLitige(
            $data['admin'],
            $data['litige']->id,
            'transfert_intervenant',
            'Arbitrage invalide'
        );
    }

    /**
     * Test 7 : Refus si la décision arbitrale est invalide
     */
    public function test_arbitrage_refuse_decision_invalide(): void
    {
        $data = $this->preparerLitigeOuvert();

        $this->expectException(DecisionLitigeInvalideException::class);
        $this->litigeService->arbitrerLitige(
            $data['admin'],
            $data['litige']->id,
            'decision_inconnue',
            'Motif test'
        );
    }

    /**
     * RG13 / RG14 — Test 8 : Support de la variante verbe 'transferer_intervenant'
     */
    public function test_arbitrage_accepte_variante_verbe_transferer_intervenant(): void
    {
        $data = $this->preparerLitigeOuvert(1);

        $litige = $this->litigeService->arbitrerLitige(
            $data['admin'],
            $data['litige']->id,
            'transferer_intervenant',
            'Variante syntaxique acceptée'
        );

        $this->assertEquals('transfert_intervenant', $litige->decision);
        $this->assertEquals('resolu', $litige->statut);
    }

    /**
     * Test 9 : Échec et rollback complet si incohérence de crédits
     */
    public function test_rollback_complet_si_erreur_pendant_arbitrage(): void
    {
        $data = $this->preparerLitigeOuvert(2);

        // Altérer artificiellement les crédits bloqués du bénéficiaire pour provoquer un échec financier
        $benefWallet = Portefeuille::where('etudiant_id', $data['beneficiaire']->id)->first();
        $benefWallet->credits_bloques = 0;
        $benefWallet->save();

        try {
            $this->litigeService->arbitrerLitige(
                $data['admin'],
                $data['litige']->id,
                'transfert_intervenant',
                'Doit échouer'
            );
            $this->fail("L'arbitrage aurait dû échouer pour crédits bloqués insuffisants.");
        } catch (\Throwable $e) {
            // Vérifier que le litige est RESTÉ 'ouvert' (rollback)
            $litigeFresh = Litige::find($data['litige']->id);
            $this->assertEquals('ouvert', $litigeFresh->statut);
            $this->assertNull($litigeFresh->decision);
            $this->assertNull($litigeFresh->admin_id);

            // Vérifier que la réservation est RESTÉE 'en_litige' (rollback)
            $reservationFresh = Reservation::find($data['reservation']->id);
            $this->assertEquals('en_litige', $reservationFresh->statut);
        }
    }

    /**
     * RG14 — Test 9b : Simulation explicite d'échec dans CreditService et rollback intégral
     */
    public function test_rollback_complet_si_exception_dans_credit_service(): void
    {
        $data = $this->preparerLitigeOuvert(2);

        $mockCreditService = $this->createMock(CreditService::class);
        $mockCreditService->expects($this->once())
            ->method('transfererCredits')
            ->willThrowException(new \RuntimeException("Panne inattendue du registre financier"));

        $serviceAvecMock = new LitigeService($mockCreditService);

        try {
            $serviceAvecMock->arbitrerLitige(
                $data['admin'],
                $data['litige']->id,
                'transfert_intervenant',
                'Arbitrage avec incident financier'
            );
            $this->fail("L'exception de CreditService aurait dû être propagée.");
        } catch (\RuntimeException $e) {
            $this->assertEquals("Panne inattendue du registre financier", $e->getMessage());

            // Vérification ACID : aucun statut modifié ne doit subsister
            $litigeFresh = Litige::find($data['litige']->id);
            $this->assertEquals('ouvert', $litigeFresh->statut);
            $this->assertNull($litigeFresh->decision);
            $this->assertNull($litigeFresh->date_resolution);

            $reservationFresh = Reservation::find($data['reservation']->id);
            $this->assertEquals('en_litige', $reservationFresh->statut);
            $this->assertFalse((bool) $reservationFresh->confirmation_intervenant);
            $this->assertFalse((bool) $reservationFresh->confirmation_beneficiaire);
        }
    }

    /**
     * RG14 — Test 10 : Absence formelle de mouvements financiers en double
     */
    public function test_absence_mouvements_financiers_en_double(): void
    {
        $data = $this->preparerLitigeOuvert(2);

        // Avant arbitrage : 1 seul mouvement (le blocage initial)
        $this->assertEquals(1, MouvementCredit::where('reservation_id', $data['reservation']->id)->count());

        // Arbitrage vers intervenant
        $this->litigeService->arbitrerLitige(
            $data['admin'],
            $data['litige']->id,
            'transfert_intervenant',
            'Jugement favorable intervenant'
        );

        // Après arbitrage : exactement 3 mouvements (1 blocage initial + 2 transferts)
        $mvts = MouvementCredit::where('reservation_id', $data['reservation']->id)->get();
        $this->assertCount(3, $mvts);
        $this->assertEquals(1, $mvts->where('type', 'blocage')->count());
        $this->assertEquals(2, $mvts->where('type', 'transfert_gain')->count());

        // Une tentative ultérieure de ré-arbitrage échoue et ne crée AUCUN nouveau mouvement
        try {
            $this->litigeService->arbitrerLitige(
                $data['admin'],
                $data['litige']->id,
                'restitution_beneficiaire',
                'Fraude'
            );
        } catch (\Throwable $e) {
            // Attendu
        }

        // Le compte de mouvements reste strictement identique à 3
        $this->assertEquals(3, MouvementCredit::where('reservation_id', $data['reservation']->id)->count());
    }
}
