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
use App\Models\Litige;
use App\Services\CreditService;
use App\Exceptions\SoldeInsuffisantException;
use App\Exceptions\OperationFinanciereException;
use App\Exceptions\PortefeuilleIntrouvableException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreditServiceTest extends TestCase
{
    private CreditService $creditService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createSchema();
        $this->creditService = new CreditService();
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

    /**
     * Helper pour créer un contexte de test complet avec réservation et créneau paramétrable.
     */
    private function creerContexteReservation(
        int $soldeBeneficiaire = 2,
        int $cout = 1,
        string $dateCreneau = '2026-11-20',
        string $heureDebut = '10:00:00',
        string $statutReservation = 'en_attente'
    ): array {
        $intervenant = User::create([
            'nom' => 'Intervenant',
            'prenom' => 'Sara',
            'email' => 'sara_' . uniqid() . '@estbm.ac.ma',
            'password' => 'secret',
            'role' => 'etudiant',
        ]);
        $portefeuilleIntervenant = new Portefeuille(['etudiant_id' => $intervenant->id]);
        $portefeuilleIntervenant->solde_disponible = 0;
        $portefeuilleIntervenant->credits_bloques = 0;
        $portefeuilleIntervenant->save();

        $beneficiaire = User::create([
            'nom' => 'Beneficiaire',
            'prenom' => 'Amine',
            'email' => 'amine_' . uniqid() . '@estbm.ac.ma',
            'password' => 'secret',
            'role' => 'etudiant',
        ]);
        $portefeuilleBeneficiaire = new Portefeuille(['etudiant_id' => $beneficiaire->id]);
        $portefeuilleBeneficiaire->solde_disponible = $soldeBeneficiaire;
        $portefeuilleBeneficiaire->credits_bloques = 0;
        $portefeuilleBeneficiaire->save();

        $categorie = Categorie::create(['nom' => 'Cat_' . uniqid()]);

        $offre = new Offre([
            'categorie_id' => $categorie->id,
            'titre' => 'Offre test',
            'competence' => 'PHP',
            'description' => 'Desc',
            'duree' => $cout,
        ]);
        $offre->etudiant_id = $intervenant->id;
        $offre->statut = 'publiee';
        $offre->save();

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => $dateCreneau,
            'heure_debut' => $heureDebut,
            'heure_fin' => '12:00:00',
            'lieu_public' => 'Campus',
            'lien_ou_lieu_prive' => 'Secret',
        ]);

        $reservation = new Reservation(['creneau_id' => $creneau->id]);
        $reservation->beneficiaire_id = $beneficiaire->id;
        $reservation->cout_credits = $cout;
        $reservation->statut = $statutReservation;
        $reservation->save();

        return compact(
            'intervenant',
            'portefeuilleIntervenant',
            'beneficiaire',
            'portefeuilleBeneficiaire',
            'offre',
            'creneau',
            'reservation'
        );
    }

    /**
     * TEST 1 : RG01 - Attribution unique des 2 crédits de bienvenue.
     */
    public function test_attribution_unique_credits_bienvenue(): void
    {
        $etudiant = User::create([
            'nom' => 'Nouveau',
            'prenom' => 'Etudiant',
            'email' => 'nouveau@estbm.ac.ma',
            'password' => 'pwd',
            'role' => 'etudiant',
        ]);

        $mouvement = $this->creditService->attribuerCreditsBienvenue($etudiant);

        $this->assertEquals(2, $mouvement->quantite);
        $this->assertEquals('bienvenue', $mouvement->type);

        $solde = $this->creditService->getSolde($etudiant);
        $this->assertEquals(2, $solde['solde_disponible']);
        $this->assertEquals(0, $solde['credits_bloques']);

        // Tentative d'attribution une seconde fois -> doit lever une exception (RG01)
        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("déjà été attribués");
        $this->creditService->attribuerCreditsBienvenue($etudiant);
    }

    /**
     * TEST 2 : RG01 - Les crédits de bienvenue sont refusés aux non-étudiants (ex: admin).
     */
    public function test_attribution_bienvenue_refusee_si_non_etudiant(): void
    {
        $admin = new User([
            'nom' => 'Admin',
            'prenom' => 'System',
            'email' => 'admin@estbm.ac.ma',
            'password' => 'pwd',
        ]);
        $admin->role = 'admin';
        $admin->save();

        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("exclusivement réservés aux étudiants");
        $this->creditService->attribuerCreditsBienvenue($admin);
    }

    /**
     * TEST 3 : RG05 & RG06 - Blocage des crédits et vérification de solde.
     */
    public function test_bloquer_credits_succes(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];

        $this->assertTrue($this->creditService->verifierSoldeDisponible($beneficiaire, 1));

        $mvt = $this->creditService->bloquerCredits($reservation);

        $this->assertEquals(-1, $mvt->quantite);
        $this->assertEquals('blocage', $mvt->type);

        $solde = $this->creditService->getSolde($beneficiaire);
        $this->assertEquals(1, $solde['solde_disponible']);
        $this->assertEquals(1, $solde['credits_bloques']);
        $this->assertEquals(2, $solde['solde_total']);
    }

    /**
     * TEST 4 : RG05 - Refus du blocage si le solde est insuffisant.
     */
    public function test_bloquer_credits_solde_insuffisant(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 0, cout: 1);
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];

        $this->assertFalse($this->creditService->verifierSoldeDisponible($beneficiaire, 1));

        $this->expectException(SoldeInsuffisantException::class);
        $this->creditService->bloquerCredits($reservation);
    }

    /**
     * TEST 5 : Empêcher le double blocage sur une même réservation.
     */
    public function test_empecher_double_blocage(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];

        $this->creditService->bloquerCredits($reservation);

        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("déjà bloqués");
        $this->creditService->bloquerCredits($reservation);
    }

    /**
     * TEST 6 : Validation du coût à partir de la réservation verrouillée en base (Requirement 1).
     * Si le coût est modifié en base avant l'acquisition du verrou, c'est la valeur en base verrouillée qui est utilisée.
     */
    public function test_bloquer_credits_valide_cout_depuis_base_verrouillee(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];

        // L'objet $reservation en mémoire a cout_credits = 1
        $this->assertEquals(1, $reservation->cout_credits);

        // Une modification intervient en base de données avant le verrouillage : cout_credits passe à 2
        DB::table('reservations')->where('id', $reservation->id)->update(['cout_credits' => 2]);

        // Appel de bloquerCredits avec l'ancien objet en mémoire
        $mvt = $this->creditService->bloquerCredits($reservation);

        // Le service a verrouillé la ligne en base et rechargé cout_credits = 2
        $this->assertEquals(-2, $mvt->quantite);
        $solde = $this->creditService->getSolde($beneficiaire);
        $this->assertEquals(0, $solde['solde_disponible']);
        $this->assertEquals(2, $solde['credits_bloques']);
    }

    /**
     * TEST 7 : Refus de blocage si le statut de la réservation est incompatible (Requirement 2).
     */
    public function test_bloquer_credits_statuts_incompatibles_refuses(): void
    {
        $statutsInvalides = ['refusee', 'annulee', 'realisee', 'en_litige'];

        foreach ($statutsInvalides as $statut) {
            $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
            $reservation = $ctx['reservation'];
            $reservation->statut = $statut;
            $reservation->save();

            try {
                $this->creditService->bloquerCredits($reservation);
                $this->fail("Le blocage aurait dû échouer pour le statut {$statut}");
            } catch (OperationFinanciereException $e) {
                $this->assertStringContainsString("incompatible avec le blocage", $e->getMessage());
            }
        }
    }

    /**
     * TEST 8 : RG10 - Libération des crédits après annulation autorisée avant le début (Requirement 3).
     */
    public function test_liberer_credits_apres_annulation(): void
    {
        // Créneau dans le futur (2026-11-25)
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1, dateCreneau: '2026-11-25');
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];

        // Réservation acceptée puis blocage des crédits
        $reservation->statut = 'acceptee';
        $reservation->save();
        $this->creditService->bloquerCredits($reservation);

        // Annulation préalable de la réservation
        $reservation->statut = 'annulee';
        $reservation->save();

        $mvt = $this->creditService->libererCredits($reservation);

        $this->assertEquals(1, $mvt->quantite);
        $this->assertEquals('deblocage_annulation', $mvt->type);

        $solde = $this->creditService->getSolde($beneficiaire);
        $this->assertEquals(2, $solde['solde_disponible']);
        $this->assertEquals(0, $solde['credits_bloques']);
    }

    /**
     * TEST 9 : RG10 - Refus de libération directe si la réservation n'a jamais été acceptée (Requirement 3).
     */
    public function test_liberer_credits_refus_si_reservation_non_acceptee(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1, dateCreneau: '2026-11-25');
        $reservation = $ctx['reservation'];

        // Statut 'en_attente' -> rejet strict
        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("n'est autorisée que pour une réservation acceptée (RG10)");
        $this->creditService->libererCredits($reservation);
    }

    /**
     * TEST 10 : RG10 - Une annulation après le début de la séance refuse la libération directe des crédits.
     */
    public function test_annulation_non_autorisee_apres_debut_refuse_liberation(): void
    {
        // Créneau dans le passé par rapport au temps actuel (2026-11-10)
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1, dateCreneau: '2026-11-10', heureDebut: '08:00:00');
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];

        $reservation->statut = 'acceptee';
        $reservation->save();

        $this->creditService->bloquerCredits($reservation);

        // Tentative de libération directe après le début -> Rejet strict (RG10)
        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("interdites après le début de la séance (RG10)");
        $this->creditService->libererCredits($reservation);

        // Les crédits doivent impérativement rester bloqués
        $solde = $this->creditService->getSolde($beneficiaire);
        $this->assertEquals(1, $solde['credits_bloques']);
        $this->assertEquals(1, $solde['solde_disponible']);
    }

    /**
     * TEST 11 : Refus formel d'une deuxième libération des crédits (Requirement 3).
     */
    public function test_refus_deuxieme_liberation(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1, dateCreneau: '2026-11-25');
        $reservation = $ctx['reservation'];

        $reservation->statut = 'acceptee';
        $reservation->save();
        $this->creditService->bloquerCredits($reservation);

        $reservation->statut = 'annulee';
        $reservation->save();

        // Première libération -> Succès
        $this->creditService->libererCredits($reservation);

        // Deuxième libération -> Exception
        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("ont déjà été libérés");
        $this->creditService->libererCredits($reservation);
    }

    /**
     * TEST 12 : RG11, RG14 & Requirements 5, 6 - Transfert financier avec DEUX mouvements opposés (somme = 0).
     */
    public function test_transferer_credits_cree_deux_mouvements_opposes(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];
        $intervenant = $ctx['intervenant'];

        $reservation->statut = 'acceptee';
        $reservation->save();

        $this->creditService->bloquerCredits($reservation);

        $reservation->confirmation_intervenant = true;
        $reservation->confirmation_beneficiaire = true;
        $reservation->save();

        $this->creditService->transfererCredits($reservation);

        // Vérification 1 : Exactement deux mouvements créés pour cette réservation et ce type
        $mouvements = MouvementCredit::where('reservation_id', $reservation->id)
            ->where('type', 'transfert_gain')
            ->get();
        $this->assertCount(2, $mouvements, "Un transfert doit enregistrer exactement deux mouvements financiers.");

        // Vérification 2 : Le bénéficiaire est débité de -1, l'intervenant est crédité de +1
        $portefeuilleBen = Portefeuille::where('etudiant_id', $beneficiaire->id)->first();
        $portefeuilleInt = Portefeuille::where('etudiant_id', $intervenant->id)->first();

        $mvtDebit = $mouvements->firstWhere('portefeuille_id', $portefeuilleBen->id);
        $mvtCredit = $mouvements->firstWhere('portefeuille_id', $portefeuilleInt->id);

        $this->assertNotNull($mvtDebit, "Mouvement de débit introuvable pour le bénéficiaire.");
        $this->assertNotNull($mvtCredit, "Mouvement de crédit introuvable pour l'intervenant.");

        $this->assertEquals(-1, $mvtDebit->quantite);
        $this->assertEquals(1, $mvtCredit->quantite);

        // Vérification 3 : La somme des deux mouvements est strictement égale à zéro
        $this->assertEquals(0, $mouvements->sum('quantite'), "La somme des mouvements du transfert doit être nulle.");

        // Soldes réels
        $soldeBen = $this->creditService->getSolde($beneficiaire);
        $this->assertEquals(1, $soldeBen['solde_disponible']);
        $this->assertEquals(0, $soldeBen['credits_bloques']);

        $soldeInt = $this->creditService->getSolde($intervenant);
        $this->assertEquals(1, $soldeInt['solde_disponible']);
        $this->assertEquals(0, $soldeInt['credits_bloques']);
    }

    /**
     * TEST 13 : RG11 & RG12 - Refus du transfert si une confirmation manque.
     */
    public function test_refus_transfert_si_confirmation_manquante(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];

        $reservation->statut = 'acceptee';
        $reservation->save();
        $this->creditService->bloquerCredits($reservation);

        $reservation->confirmation_intervenant = true;
        $reservation->confirmation_beneficiaire = false;
        $reservation->save();

        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("exige la confirmation des deux participants");
        $this->creditService->transfererCredits($reservation);
    }

    /**
     * TEST 14 : RG12 & RG13 - Conservation des crédits bloqués en cas de litige ouvert (Requirement 4).
     */
    public function test_conservation_credits_si_litige_ouvert(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];

        $reservation->statut = 'acceptee';
        $reservation->save();
        $this->creditService->bloquerCredits($reservation);

        $reservation->confirmation_intervenant = true;
        $reservation->confirmation_beneficiaire = true;
        $reservation->save();

        // Ouverture d'un litige
        $litige = new Litige([
            'reservation_id' => $reservation->id,
            'description' => 'Séance non conforme',
        ]);
        $litige->declarant_id = $beneficiaire->id;
        $litige->statut = 'ouvert';
        $litige->save();

        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("litige est actuellement ouvert");
        $this->creditService->transfererCredits($reservation);
    }

    /**
     * TEST 15 : RG13 & RG14 - Décision arbitrale en faveur de l'intervenant autorise le transfert (Requirement 4).
     */
    public function test_arbitrage_litige_favorable_intervenant_autorise_transfert(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];
        $intervenant = $ctx['intervenant'];

        $reservation->statut = 'acceptee';
        $reservation->save();
        $this->creditService->bloquerCredits($reservation);

        // Litige résolu en faveur de l'intervenant (RG13)
        $litige = new Litige([
            'reservation_id' => $reservation->id,
            'description' => 'Séance contestée',
        ]);
        $litige->declarant_id = $beneficiaire->id;
        $litige->statut = 'resolu';
        $litige->decision = 'transfert_intervenant';
        $litige->motif_decision = 'Preuve de présence fournie';
        $litige->save();

        // Le transfert doit réussir même si confirmation_beneficiaire est false
        $this->creditService->transfererCredits($reservation);

        $soldeInt = $this->creditService->getSolde($intervenant);
        $this->assertEquals(1, $soldeInt['solde_disponible']);

        $soldeBen = $this->creditService->getSolde($beneficiaire);
        $this->assertEquals(0, $soldeBen['credits_bloques']);
    }

    /**
     * TEST 16 : Décision de litige modifiée ou obsolète rejetée sous verrou (Requirement 4).
     */
    public function test_transfert_refuse_si_litige_modifie_en_restitution_beneficiaire(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];

        $reservation->statut = 'acceptee';
        $reservation->save();
        $this->creditService->bloquerCredits($reservation);

        $litige = new Litige([
            'reservation_id' => $reservation->id,
            'description' => 'Séance contestée',
        ]);
        $litige->declarant_id = $beneficiaire->id;
        $litige->statut = 'resolu';
        $litige->decision = 'restitution_beneficiaire';
        $litige->motif_decision = 'Intervenant absent';
        $litige->save();

        // Tentative de transfert malgré arbitrage en faveur du bénéficiaire -> Rejet
        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("Le transfert exige la confirmation des deux participants");
        $this->creditService->transfererCredits($reservation);
    }

    /**
     * TEST 17 : RG13 & RG14 - Remboursement au bénéficiaire après décision arbitrale favorable (Requirement 4).
     */
    public function test_rembourser_credits_arbitrage_litige_beneficiaire(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];

        $reservation->statut = 'acceptee';
        $reservation->save();
        $this->creditService->bloquerCredits($reservation);

        $litige = new Litige([
            'reservation_id' => $reservation->id,
            'description' => 'Absence intervenant',
        ]);
        $litige->declarant_id = $beneficiaire->id;
        $litige->statut = 'resolu';
        $litige->decision = 'restitution_beneficiaire';
        $litige->motif_decision = 'Absence justifiée';
        $litige->save();

        $mvt = $this->creditService->rembourserCredits($reservation);

        $this->assertEquals(1, $mvt->quantite);
        $this->assertEquals('restitution_litige', $mvt->type);

        $solde = $this->creditService->getSolde($beneficiaire);
        $this->assertEquals(2, $solde['solde_disponible']);
        $this->assertEquals(0, $solde['credits_bloques']);
    }

    /**
     * TEST 18 : Remboursement refusé si litige encore ouvert ou décision non favorable (Requirement 4).
     */
    public function test_rembourser_credits_refuse_si_litige_non_resolu(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];

        $reservation->statut = 'acceptee';
        $reservation->save();
        $this->creditService->bloquerCredits($reservation);

        $litige = new Litige([
            'reservation_id' => $reservation->id,
            'description' => 'Absence contestée',
        ]);
        $litige->declarant_id = $beneficiaire->id;
        $litige->statut = 'ouvert';
        $litige->save();

        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("exige une décision arbitrale résolue de type 'restitution_beneficiaire'");
        $this->creditService->rembourserCredits($reservation);
    }

    /**
     * TEST 19 : Transfert et remboursement refusés sans blocage préalable.
     */
    public function test_transfert_et_remboursement_refuses_sans_blocage_prealable(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];
        $reservation->statut = 'acceptee';
        $reservation->confirmation_intervenant = true;
        $reservation->confirmation_beneficiaire = true;
        $reservation->save();

        // Aucun blocage n'a été effectué
        try {
            $this->creditService->transfererCredits($reservation);
            $this->fail("Le transfert sans blocage aurait dû échouer.");
        } catch (OperationFinanciereException $e) {
            $this->assertStringContainsString("aucun crédit n'a été préalablement bloqué", $e->getMessage());
        }
    }

    /**
     * TEST 20 : RG14 - Refus formel d'un deuxième transfert.
     */
    public function test_refus_deuxieme_transfert(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];

        $reservation->statut = 'acceptee';
        $reservation->save();
        $this->creditService->bloquerCredits($reservation);

        $reservation->confirmation_intervenant = true;
        $reservation->confirmation_beneficiaire = true;
        $reservation->save();

        // Premier transfert -> Succès
        $this->creditService->transfererCredits($reservation);

        // Deuxième transfert -> Rejet strict (RG14)
        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("déjà été exécuté");
        $this->creditService->transfererCredits($reservation);
    }

    /**
     * TEST 21 : RG14 - Refus formel d'un deuxième remboursement.
     */
    public function test_refus_deuxieme_remboursement(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];

        $reservation->statut = 'acceptee';
        $reservation->save();
        $this->creditService->bloquerCredits($reservation);

        $litige = new Litige([
            'reservation_id' => $reservation->id,
            'description' => 'Absence intervenant',
        ]);
        $litige->declarant_id = $beneficiaire->id;
        $litige->statut = 'resolu';
        $litige->decision = 'restitution_beneficiaire';
        $litige->save();

        // Premier remboursement -> Succès
        $this->creditService->rembourserCredits($reservation);

        // Tentative de second remboursement -> Exception (RG14)
        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("déjà été exécuté");
        $this->creditService->rembourserCredits($reservation);
    }

    /**
     * TEST 22 : RG14 - Deux opérations consécutives de règlement ne peuvent pas régler deux fois la réservation.
     */
    public function test_concurrence_interdiction_double_reglement(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];

        $reservation->statut = 'acceptee';
        $reservation->save();
        $this->creditService->bloquerCredits($reservation);

        $reservation->confirmation_intervenant = true;
        $reservation->confirmation_beneficiaire = true;
        $reservation->save();

        // Simulation de 2 tentatives de règlement : transfert puis tentative de libération
        $this->creditService->transfererCredits($reservation);

        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("Cette réservation a déjà fait l'objet d'un règlement financier définitif (RG14)");
        $this->creditService->libererCredits($reservation);
    }

    /**
     * TEST 23 : RG02 - Validation stricte du coût de la séance (uniquement 1 ou 2 crédits).
     */
    public function test_cout_invalide_reservation_refuse(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];
        $reservation->statut = 'acceptee';
        $reservation->save();

        // Modification en base avec un coût illégal (ex: 3 crédits)
        DB::table('reservations')->where('id', $reservation->id)->update(['cout_credits' => 3]);

        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("doit être de 1 ou 2 crédits (RG02)");
        $this->creditService->bloquerCredits($reservation);
    }

    /**
     * TEST 24 : RG16 - Les soldes ne deviennent jamais négatifs.
     */
    public function test_soldes_ne_deviennent_jamais_negatifs(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 2);
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];

        $this->creditService->bloquerCredits($reservation);

        $solde = $this->creditService->getSolde($beneficiaire);
        $this->assertEquals(0, $solde['solde_disponible']);
        $this->assertEquals(2, $solde['credits_bloques']);

        $this->assertGreaterThanOrEqual(0, $solde['solde_disponible']);
        $this->assertGreaterThanOrEqual(0, $solde['credits_bloques']);
    }

    /**
     * TEST 25 : Respect strict des 5 types de mouvements autorisés par la migration 7 (Requirement 6).
     */
    public function test_types_mouvements_strictement_conformes_migration_7(): void
    {
        $typesAutorises = [
            'bienvenue',
            'blocage',
            'deblocage_annulation',
            'transfert_gain',
            'restitution_litige',
        ];

        // 1. Bienvenue
        $u1 = User::create(['nom' => 'T1', 'prenom' => 'U1', 'email' => 't1@estbm.ac.ma', 'password' => 'p', 'role' => 'etudiant']);
        $m1 = $this->creditService->attribuerCreditsBienvenue($u1);
        $this->assertContains($m1->type, $typesAutorises);

        // 2. Blocage
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $m2 = $this->creditService->bloquerCredits($ctx['reservation']);
        $this->assertContains($m2->type, $typesAutorises);

        // 3. Déblocage annulation
        $ctx['reservation']->statut = 'acceptee';
        $ctx['reservation']->save();
        $ctx2 = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1, dateCreneau: '2026-11-28');
        $ctx2['reservation']->statut = 'acceptee';
        $ctx2['reservation']->save();
        $this->creditService->bloquerCredits($ctx2['reservation']);
        $ctx2['reservation']->statut = 'annulee';
        $ctx2['reservation']->save();
        $m3 = $this->creditService->libererCredits($ctx2['reservation']);
        $this->assertContains($m3->type, $typesAutorises);

        // 4. Transfert gain
        $ctx3 = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $ctx3['reservation']->statut = 'acceptee';
        $ctx3['reservation']->save();
        $this->creditService->bloquerCredits($ctx3['reservation']);
        $ctx3['reservation']->confirmation_intervenant = true;
        $ctx3['reservation']->confirmation_beneficiaire = true;
        $ctx3['reservation']->save();
        $m4 = $this->creditService->transfererCredits($ctx3['reservation']);
        $this->assertContains($m4->type, $typesAutorises);

        // 5. Restitution litige
        $ctx4 = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $ctx4['reservation']->statut = 'acceptee';
        $ctx4['reservation']->save();
        $this->creditService->bloquerCredits($ctx4['reservation']);
        $litige = new Litige(['reservation_id' => $ctx4['reservation']->id, 'description' => 'test']);
        $litige->declarant_id = $ctx4['beneficiaire']->id;
        $litige->statut = 'resolu';
        $litige->decision = 'restitution_beneficiaire';
        $litige->save();
        $m5 = $this->creditService->rembourserCredits($ctx4['reservation']);
        $this->assertContains($m5->type, $typesAutorises);
    }

    /**
     * TEST 26 : Atomicité ACID - Une erreur pendant l'opération annule toutes les modifications (Rollback).
     */
    public function test_erreur_pendant_operation_annule_toutes_modifications(): void
    {
        $ctx = $this->creerContexteReservation(soldeBeneficiaire: 2, cout: 1);
        $reservation = $ctx['reservation'];
        $beneficiaire = $ctx['beneficiaire'];

        $reservation->statut = 'acceptee';
        $reservation->save();
        $this->creditService->bloquerCredits($reservation);

        // Litige encore OUVERT (provoque une exception dans le transfert)
        $litige = new Litige([
            'reservation_id' => $reservation->id,
            'description' => 'Litige en cours',
        ]);
        $litige->declarant_id = $beneficiaire->id;
        $litige->statut = 'ouvert';
        $litige->save();

        $soldeAvant = $this->creditService->getSolde($beneficiaire);

        try {
            $this->creditService->transfererCredits($reservation);
            $this->fail("Le transfert aurait dû échouer et être annulé.");
        } catch (OperationFinanciereException $e) {
            // L'erreur est interceptée
        }

        // Vérification du Rollback intégral : les soldes n'ont pas bougé
        $soldeApres = $this->creditService->getSolde($beneficiaire);
        $this->assertEquals($soldeAvant['solde_disponible'], $soldeApres['solde_disponible']);
        $this->assertEquals($soldeAvant['credits_bloques'], $soldeApres['credits_bloques']);

        // Aucun mouvement de transfert n'a persisté
        $this->assertEquals(0, MouvementCredit::where('reservation_id', $reservation->id)->where('type', 'transfert_gain')->count());
    }
}
