<?php

namespace Tests\Unit;

use App\Exceptions\AccesReservationNonAutoriseException;
use App\Exceptions\AutoReservationException;
use App\Exceptions\CompteDesactiveException;
use App\Exceptions\ConfirmationSeanceException;
use App\Exceptions\ConflitHoraireException;
use App\Exceptions\CreneauIndisponibleException;
use App\Exceptions\DelaiAnnulationDepasseException;
use App\Exceptions\OffreInvalideException;
use App\Exceptions\SoldeInsuffisantPourReservationException;
use App\Exceptions\StatutReservationInvalideException;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReservationServiceTest extends TestCase
{
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

    private function creerOffreEtCreneau(User $intervenant, int $duree = 1, string $statutOffre = 'publiee', ?Carbon $dateHeureDebut = null): Creneau
    {
        $cat = Categorie::firstOrCreate(['nom' => 'Informatique']);

        $offre = new Offre();
        $offre->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $cat->id,
            'titre' => 'Initiation Laravel',
            'competence' => 'PHP/Laravel',
            'description' => 'Séance d\'initiation à Laravel',
            'niveau' => 'debutant',
            'duree' => $duree,
            'mode' => 'presentiel',
            'statut' => $statutOffre,
        ]);
        $offre->save();

        $debut = $dateHeureDebut ?? Carbon::now()->addDays(2)->setTime(14, 0);
        $fin = (clone $debut)->addHours($duree);

        $creneau = new Creneau();
        $creneau->forceFill([
            'offre_id' => $offre->id,
            'date' => $debut->toDateString(),
            'heure_debut' => $debut->format('H:i:s'),
            'heure_fin' => $fin->format('H:i:s'),
            'lieu_public' => 'Salle B201',
            'lien_ou_lieu_prive' => 'https://meet.skillswap.edu/laravel-prive',
        ]);
        $creneau->save();

        return $creneau;
    }

    /**
     * RG02 à RG05 : Demande de réservation réussie
     */
    public function test_demande_reservation_reussie_cree_statut_en_attente_sans_bloquer_credits(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 3);
        $creneau = $this->creerOffreEtCreneau($intervenant, 2);

        $reservation = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        $this->assertEquals('en_attente', $reservation->statut);
        $this->assertEquals(2, $reservation->cout_credits); // RG02 : coût égal à la durée
        $this->assertEquals($beneficiaire->id, $reservation->beneficiaire_id);

        // RG05 : Aucun crédit bloqué à ce stade
        $portefeuille = Portefeuille::where('etudiant_id', $beneficiaire->id)->first();
        $this->assertEquals(3, $portefeuille->solde_disponible);
        $this->assertEquals(0, $portefeuille->credits_bloques);
    }

    /**
     * RG03 : Interdiction formelle d'auto-réservation
     */
    public function test_interdiction_auto_reservation_lance_exception(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $this->expectException(AutoReservationException::class);
        $this->reservationService->demanderReservation($intervenant, $creneau->id);
    }

    /**
     * RG03 : Double demande active interdite sur le même créneau
     */
    public function test_double_demande_active_meme_creneau_lance_exception(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 5);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        $this->expectException(AutoReservationException::class);
        $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
    }

    /**
     * RG04 : Créneau passé interdit
     */
    public function test_demande_sur_creneau_passe_lance_exception(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 5);

        $debutPasse = Carbon::now()->subDays(1)->setTime(10, 0);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1, 'publiee', $debutPasse);

        $this->expectException(CreneauIndisponibleException::class);
        $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
    }

    /**
     * RG04 : Offre non publiée interdite
     */
    public function test_demande_sur_offre_non_publiee_lance_exception(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 5);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1, 'brouillon');

        $this->expectException(OffreInvalideException::class);
        $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
    }

    /**
     * RG05 : Solde disponible insuffisant
     */
    public function test_demande_refusee_si_solde_insuffisant(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 0); // 0 crédit
        $creneau = $this->creerOffreEtCreneau($intervenant, 1);

        $this->expectException(SoldeInsuffisantPourReservationException::class);
        $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
    }

    /**
     * RG06 : Acceptation et blocage des crédits
     */
    public function test_acceptation_par_intervenant_bloque_credits_et_change_statut(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant, 2);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        $resAcceptee = $this->reservationService->accepterReservation($intervenant, $res->id);

        $this->assertEquals('acceptee', $resAcceptee->statut);

        // RG06 : Les crédits du bénéficiaire doivent être séquestrés
        $portefeuille = Portefeuille::where('etudiant_id', $beneficiaire->id)->first();
        $this->assertEquals(2, $portefeuille->solde_disponible); // 4 - 2
        $this->assertEquals(2, $portefeuille->credits_bloques);

        // Mouvement de type blocage enregistré
        $mvt = MouvementCredit::where('reservation_id', $res->id)->where('type', 'blocage')->first();
        $this->assertNotNull($mvt);
        $this->assertEquals(-2, $mvt->quantite);
    }

    /**
     * RG06 : Acceptation par un tiers non-intervenant rejetée
     */
    public function test_acceptation_par_un_tiers_lance_exception(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);
        $usurpateur = $this->creerUtilisateur('usurpateur@univ.fr', 'etudiant', 'actif', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        $this->expectException(AccesReservationNonAutoriseException::class);
        $this->reservationService->accepterReservation($usurpateur, $res->id);
    }

    /**
     * RG07 : Refus automatique de toutes les autres demandes concurrentes du même créneau
     */
    public function test_rg07_acceptation_refuse_automatiquement_autres_demandes_en_attente(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire1 = $this->creerUtilisateur('ben1@univ.fr', 'etudiant', 'actif', 4);
        $beneficiaire2 = $this->creerUtilisateur('ben2@univ.fr', 'etudiant', 'actif', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1);

        $res1 = $this->reservationService->demanderReservation($beneficiaire1, $creneau->id);
        $res2 = $this->reservationService->demanderReservation($beneficiaire2, $creneau->id);

        // L'intervenant retient beneficiaire1
        $this->reservationService->accepterReservation($intervenant, $res1->id);

        // Vérification que res2 a été automatiquement refusée avec le motif RG07
        $res2Fresh = Reservation::find($res2->id);
        $this->assertEquals('refusee', $res2Fresh->statut);
        $this->assertStringContainsString('RG07', $res2Fresh->motif_refus);

        // Les crédits de beneficiaire2 ne doivent PAS avoir été touchés
        $wallet2 = Portefeuille::where('etudiant_id', $beneficiaire2->id)->first();
        $this->assertEquals(4, $wallet2->solde_disponible);
        $this->assertEquals(0, $wallet2->credits_bloques);
    }

    /**
     * RG08 : Prévention des conflits d'horaires pour le bénéficiaire
     */
    public function test_rg08_conflit_horaire_beneficiaire_empeche_nouvelle_demande(): void
    {
        $intervenant1 = $this->creerUtilisateur('inter1@univ.fr', 'etudiant', 'actif', 5);
        $intervenant2 = $this->creerUtilisateur('inter2@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('ben@univ.fr', 'etudiant', 'actif', 5);

        // Créneau 1 : 14h00 à 15h00 le lendemain
        $debut1 = Carbon::tomorrow()->setTime(14, 0);
        $creneau1 = $this->creerOffreEtCreneau($intervenant1, 1, 'publiee', $debut1);

        // Créneau 2 : 14h30 à 15h30 le même jour (chevauchement)
        $debut2 = Carbon::tomorrow()->setTime(14, 30);
        $creneau2 = $this->creerOffreEtCreneau($intervenant2, 1, 'publiee', $debut2);

        // Bénéficiaire réserve et est accepté sur le créneau 1
        $res1 = $this->reservationService->demanderReservation($beneficiaire, $creneau1->id);
        $this->reservationService->accepterReservation($intervenant1, $res1->id);

        // Tentative de demander le créneau 2 qui chevauche
        $this->expectException(ConflitHoraireException::class);
        $this->reservationService->demanderReservation($beneficiaire, $creneau2->id);
    }

    /**
     * RG08 : Deux séances consécutives sont autorisées (14h-15h puis 15h-16h)
     */
    public function test_rg08_seances_consecutives_autorisees(): void
    {
        $intervenant1 = $this->creerUtilisateur('inter1@univ.fr', 'etudiant', 'actif', 5);
        $intervenant2 = $this->creerUtilisateur('inter2@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('ben@univ.fr', 'etudiant', 'actif', 5);

        // Créneau 1 : 14h00 à 15h00
        $debut1 = Carbon::tomorrow()->setTime(14, 0);
        $creneau1 = $this->creerOffreEtCreneau($intervenant1, 1, 'publiee', $debut1);

        // Créneau 2 : 15h00 à 16h00 (strictement consécutif)
        $debut2 = Carbon::tomorrow()->setTime(15, 0);
        $creneau2 = $this->creerOffreEtCreneau($intervenant2, 1, 'publiee', $debut2);

        $res1 = $this->reservationService->demanderReservation($beneficiaire, $creneau1->id);
        $this->reservationService->accepterReservation($intervenant1, $res1->id);

        // Demande du créneau 2 consécutif : doit réussir sans conflit
        $res2 = $this->reservationService->demanderReservation($beneficiaire, $creneau2->id);
        $this->assertEquals('en_attente', $res2->statut);
    }

    /**
     * RG09 : Refus de demande par intervenant avec motif
     */
    public function test_rg09_refus_par_intervenant_met_statut_refusee_sans_mouvement_financier(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        $refusee = $this->reservationService->refuserReservation($intervenant, $res->id, "Indisponibilité imprévue");

        $this->assertEquals('refusee', $refusee->statut);
        $this->assertEquals("Indisponibilité imprévue", $refusee->motif_refus);

        $portefeuille = Portefeuille::where('etudiant_id', $beneficiaire->id)->first();
        $this->assertEquals(4, $portefeuille->solde_disponible);
        $this->assertEquals(0, $portefeuille->credits_bloques);
    }

    /**
     * RG09 : Retrait de demande par bénéficiaire
     */
    public function test_rg09_retrait_par_beneficiaire_annule_demande(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        $annulee = $this->reservationService->retirerDemandeEnAttente($beneficiaire, $res->id);

        $this->assertEquals('annulee', $annulee->statut);
        $this->assertEquals(0, MouvementCredit::where('reservation_id', $res->id)->count());
    }

    /**
     * RG10 : Annulation d'une réservation acceptée avant séance libère intégralement les crédits
     */
    public function test_rg10_annulation_acceptee_avant_seance_libere_credits(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant, 2);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        // Le bénéficiaire annule avant la séance (créneau dans 2 jours)
        $resAnnulee = $this->reservationService->annulerReservationAcceptee($beneficiaire, $res->id, "Empêchement médical");

        $this->assertEquals('annulee', $resAnnulee->statut);

        // Crédits débloqués et restitués au solde disponible
        $portefeuille = Portefeuille::where('etudiant_id', $beneficiaire->id)->first();
        $this->assertEquals(4, $portefeuille->solde_disponible);
        $this->assertEquals(0, $portefeuille->credits_bloques);

        // Mouvement de déblocage enregistré
        $mvtDeblocage = MouvementCredit::where('reservation_id', $res->id)->where('type', 'deblocage_annulation')->first();
        $this->assertNotNull($mvtDeblocage);
        $this->assertEquals(2, $mvtDeblocage->quantite);
    }

    /**
     * RG10 : Refus formel de libération et d'annulation directe après le début de séance
     */
    public function test_rg10_refus_annulation_apres_debut_seance(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);

        // Créneau dans le futur lors de la demande et acceptation
        $debut = Carbon::now()->addHours(1);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1, 'publiee', $debut);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        // On simule le passage du temps : la séance a débuté il y a 10 minutes
        Carbon::setTestNow(Carbon::now()->addHours(1)->addMinutes(10));

        $this->expectException(DelaiAnnulationDepasseException::class);
        try {
            $this->reservationService->annulerReservationAcceptee($beneficiaire, $res->id);
        } finally {
            Carbon::setTestNow(); // Réinitialiser le mock temporel
        }
    }

    /**
     * RG11 : Confirmation unilatérale enregistrée sans transfert de crédits
     */
    public function test_rg11_confirmation_unilaterale_enregistree_credits_restent_bloques(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);

        $debut = Carbon::now()->addHours(1);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1, 'publiee', $debut);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        // Simuler la fin de séance (après 2h)
        Carbon::setTestNow(Carbon::now()->addHours(3));

        try {
            $resConf = $this->reservationService->confirmerRealisation($beneficiaire, $res->id);

            $this->assertTrue((bool) $resConf->confirmation_beneficiaire);
            $this->assertFalse((bool) $resConf->confirmation_intervenant);
            $this->assertEquals('acceptee', $resConf->statut); // Non encore 'realisee' car 1 seule confirmation

            // Crédits toujours bloqués
            $portefeuilleBenef = Portefeuille::where('etudiant_id', $beneficiaire->id)->first();
            $this->assertEquals(1, $portefeuilleBenef->credits_bloques);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * RG11 : Double confirmation bascule à 'realisee' et transfère définitivement les crédits
     */
    public function test_rg11_double_confirmation_passe_a_realisee_et_transfere_credits(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);

        $debut = Carbon::now()->addHours(1);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1, 'publiee', $debut);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        Carbon::setTestNow(Carbon::now()->addHours(3));

        try {
            // Confirmation 1 : par le bénéficiaire
            $this->reservationService->confirmerRealisation($beneficiaire, $res->id);

            // Confirmation 2 : par l'intervenant
            $resFinale = $this->reservationService->confirmerRealisation($intervenant, $res->id);

            $this->assertEquals('realisee', $resFinale->statut);
            $this->assertTrue((bool) $resFinale->confirmation_beneficiaire);
            $this->assertTrue((bool) $resFinale->confirmation_intervenant);

            // Vérification financière bilatérale
            $portefeuilleBenef = Portefeuille::where('etudiant_id', $beneficiaire->id)->first();
            $this->assertEquals(0, $portefeuilleBenef->credits_bloques); // Débité des crédits bloqués
            $this->assertEquals(3, $portefeuilleBenef->solde_disponible);

            $portefeuilleInter = Portefeuille::where('etudiant_id', $intervenant->id)->first();
            $this->assertEquals(6, $portefeuilleInter->solde_disponible); // 5 + 1 = 6

            // Vérification des deux mouvements bilatéraux de transfert
            $mvts = MouvementCredit::where('reservation_id', $res->id)->where('type', 'transfert_gain')->get();
            $this->assertCount(2, $mvts);
            $this->assertEquals(-1, $mvts->where('portefeuille_id', $portefeuilleBenef->id)->first()->quantite);
            $this->assertEquals(1, $mvts->where('portefeuille_id', $portefeuilleInter->id)->first()->quantite);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * RG11 : Rejet d'une confirmation tentée avant l'heure de fin de séance
     */
    public function test_rg11_rejet_confirmation_avant_fin_seance(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);

        $debut = Carbon::now()->addHours(2);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1, 'publiee', $debut);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        $this->expectException(ConfirmationSeanceException::class);
        $this->reservationService->confirmerRealisation($beneficiaire, $res->id);
    }

    /**
     * RG12 : Ouverture de litige après début de séance séquestre les crédits
     */
    public function test_rg12_ouverture_litige_bloque_statut_en_litige_et_sequestre_credits(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);

        $debut = Carbon::now()->addHours(1);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1, 'publiee', $debut);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        // Séance commencée
        Carbon::setTestNow(Carbon::now()->addHours(2));

        try {
            $litige = $this->reservationService->ouvrirLitige($beneficiaire, $res->id, "L'intervenant ne s'est pas présenté.");

            $this->assertEquals('ouvert', $litige->statut);
            $this->assertEquals($beneficiaire->id, $litige->declarant_id);

            $resFresh = Reservation::find($res->id);
            $this->assertEquals('en_litige', $resFresh->statut);

            // Crédits toujours séquestrés
            $wallet = Portefeuille::where('etudiant_id', $beneficiaire->id)->first();
            $this->assertEquals(1, $wallet->credits_bloques);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * RG18 : Compte désactivé formellement rejeté
     */
    public function test_rg18_compte_desactive_ne_peut_pas_demander_reservation(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaireBloque = $this->creerUtilisateur('bloque@univ.fr', 'etudiant', 'desactive', 5);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $this->expectException(CompteDesactiveException::class);
        $this->reservationService->demanderReservation($beneficiaireBloque, $creneau->id);
    }

    /**
     * RG19 : Confidentialité des coordonnées privées
     */
    public function test_rg19_confidentialite_coordonnees_privees(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 5);
        $tiers = $this->creerUtilisateur('tiers@univ.fr', 'etudiant', 'actif', 5);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        // Avant acceptation : le tiers et le bénéficiaire ne voient pas le lien privé
        $this->assertNull($creneau->getLieuOuLienSecurise($tiers));
        $this->assertNull($creneau->getLieuOuLienSecurise($beneficiaire));

        // L'intervenant propriétaire peut toujours voir le lien
        $this->assertEquals('https://meet.skillswap.edu/laravel-prive', $creneau->getLieuOuLienSecurise($intervenant));

        // Demande et acceptation
        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        // Après acceptation : le bénéficiaire a désormais accès
        $this->assertEquals('https://meet.skillswap.edu/laravel-prive', $creneau->getLieuOuLienSecurise($beneficiaire));
        // Le tiers reste rejeté
        $this->assertNull($creneau->getLieuOuLienSecurise($tiers));
    }

    /**
     * RG08 : Conflit d'horaires pour l'intervenant lors de l'acceptation
     */
    public function test_rg08_conflit_horaire_intervenant_bloque_acceptation(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $autreIntervenant = $this->creerUtilisateur('autre_inter@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 5);

        // Séance 1 : Intervenant est bénéficiaire d'une autre séance demain 14h-15h
        $debut1 = Carbon::tomorrow()->setTime(14, 0);
        $creneau1 = $this->creerOffreEtCreneau($autreIntervenant, 1, 'publiee', $debut1);
        $res1 = $this->reservationService->demanderReservation($intervenant, $creneau1->id);
        $this->reservationService->accepterReservation($autreIntervenant, $res1->id);

        // Séance 2 : Intervenant a son propre créneau demain 14h15-15h15 (chevauchement)
        $debut2 = Carbon::tomorrow()->setTime(14, 15);
        $creneau2 = $this->creerOffreEtCreneau($intervenant, 1, 'publiee', $debut2);
        $res2 = $this->reservationService->demanderReservation($beneficiaire, $creneau2->id);

        // L'intervenant tente d'accepter res2 : doit être bloqué par conflit horaire RG08
        $this->expectException(ConflitHoraireException::class);
        $this->reservationService->accepterReservation($intervenant, $res2->id);
    }

    /**
     * RG11 : Confirmation déjà effectuée par le même participant est idempotente
     */
    public function test_rg11_confirmation_deja_effectuee_meme_participant_idempotente(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);

        $debut = Carbon::now()->addHours(1);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1, 'publiee', $debut);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        Carbon::setTestNow(Carbon::now()->addHours(3));

        try {
            $conf1 = $this->reservationService->confirmerRealisation($beneficiaire, $res->id);
            $dateConf1 = $conf1->date_confirmation_beneficiaire;

            // Avancer de 5 minutes et ré-exécuter la confirmation par le même bénéficiaire
            Carbon::setTestNow(Carbon::now()->addMinutes(5));
            $conf2 = $this->reservationService->confirmerRealisation($beneficiaire, $res->id);

            $this->assertEquals($dateConf1->toIso8601String(), $conf2->date_confirmation_beneficiaire->toIso8601String());
            $this->assertEquals('acceptee', $conf2->statut);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * RG12 : Rejet d'un litige tenté avant le début de séance
     */
    public function test_rg12_rejet_litige_avant_debut_seance(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);

        $debut = Carbon::now()->addHours(2);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1, 'publiee', $debut);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        $this->expectException(StatutReservationInvalideException::class);
        $this->reservationService->ouvrirLitige($beneficiaire, $res->id, "Séance pas encore commencée");
    }

    /**
     * RG14 : Interdiction d'ouvrir deux litiges sur la même réservation
     */
    public function test_rg14_interdiction_deuxieme_litige_meme_reservation(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);

        $debut = Carbon::now()->addHours(1);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1, 'publiee', $debut);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        Carbon::setTestNow(Carbon::now()->addHours(2));

        try {
            $this->reservationService->ouvrirLitige($beneficiaire, $res->id, "Premier signalement");

            $this->expectException(StatutReservationInvalideException::class);
            $this->reservationService->ouvrirLitige($intervenant, $res->id, "Deuxième signalement concurrent");
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * RG18 : Intervenant désactivé ne peut pas accepter de réservation
     */
    public function test_rg18_intervenant_desactive_ne_peut_pas_accepter(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('beneficiaire@univ.fr', 'etudiant', 'actif', 4);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        // L'intervenant est désactivé avant acceptation
        $intervenant->statut = 'desactive';
        $intervenant->save();

        $this->expectException(CompteDesactiveException::class);
        $this->reservationService->accepterReservation($intervenant, $res->id);
    }

    /**
     * RG07 : Rejet strict d'une double acceptation sur le même créneau
     */
    public function test_rg07_double_acceptation_meme_creneau_impossible(): void
    {
        $intervenant = $this->creerUtilisateur('prof_double@univ.fr', 'etudiant', 'actif', 5);
        $eleve1 = $this->creerUtilisateur('eleve1@univ.fr', 'etudiant', 'actif', 5);
        $eleve2 = $this->creerUtilisateur('eleve2@univ.fr', 'etudiant', 'actif', 5);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $res1 = $this->reservationService->demanderReservation($eleve1, $creneau->id);
        $res2 = $this->reservationService->demanderReservation($eleve2, $creneau->id);

        // Acceptation de la première demande
        $this->reservationService->accepterReservation($intervenant, $res1->id);

        // La deuxième demande a été automatiquement refusée (RG07)
        $this->assertEquals('refusee', $res2->fresh()->statut);

        // Toute tentative ultérieure d'accepter la deuxième demande est rejetée
        $this->expectException(StatutReservationInvalideException::class);
        $this->reservationService->accepterReservation($intervenant, $res2->id);
    }

    /**
     * RG07 : Même si une demande est maintenue artificiellement en attente, l'attribution préalable bloque la 2ème acceptation
     */
    public function test_rg07_concurrence_creneau_deja_attribue_leve_creneau_indisponible(): void
    {
        $intervenant = $this->creerUtilisateur('prof_attrib@univ.fr', 'etudiant', 'actif', 5);
        $eleve1 = $this->creerUtilisateur('eleve_a@univ.fr', 'etudiant', 'actif', 5);
        $eleve2 = $this->creerUtilisateur('eleve_b@univ.fr', 'etudiant', 'actif', 5);
        $creneau = $this->creerOffreEtCreneau($intervenant);

        $res1 = $this->reservationService->demanderReservation($eleve1, $creneau->id);
        $res2 = $this->reservationService->demanderReservation($eleve2, $creneau->id);

        $this->reservationService->accepterReservation($intervenant, $res1->id);

        // Forcer en BDD res2 en 'en_attente' pour tester la barrière de verrouillage RG07 sous concurrence
        Reservation::where('id', $res2->id)->update(['statut' => 'en_attente']);

        $this->expectException(CreneauIndisponibleException::class);
        $this->reservationService->accepterReservation($intervenant, $res2->id);
    }

    /**
     * RG16 : Intégrité financière et absence formelle de solde négatif
     */
    public function test_rg16_absence_de_solde_negatif_apres_operations(): void
    {
        $intervenant = $this->creerUtilisateur('interv_solde@univ.fr', 'etudiant', 'actif', 2);
        $beneficiaire = $this->creerUtilisateur('benef_solde@univ.fr', 'etudiant', 'actif', 1);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        $portefeuilleBenef = Portefeuille::where('etudiant_id', $beneficiaire->id)->first();
        $this->assertEquals(0, $portefeuilleBenef->solde_disponible);
        $this->assertEquals(1, $portefeuilleBenef->credits_bloques);

        // Le bénéficiaire n'a plus de solde disponible : tentative d'une autre réservation bloquée
        $creneau2 = $this->creerOffreEtCreneau($intervenant, 1, 'publiee', Carbon::now()->addDays(3)->setTime(10, 0));
        $this->expectException(SoldeInsuffisantPourReservationException::class);
        $this->reservationService->demanderReservation($beneficiaire, $creneau2->id);
    }

    /**
     * Consultation paginée des réservations avec filtres (RG19)
     */
    public function test_get_mes_reservations_avec_filtres(): void
    {
        $intervenant = $this->creerUtilisateur('prof_pagin@univ.fr', 'etudiant', 'actif', 5);
        $beneficiaire = $this->creerUtilisateur('eleve_pagin@univ.fr', 'etudiant', 'actif', 5);
        $creneau = $this->creerOffreEtCreneau($intervenant, 1);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);

        $mesResBenef = $this->reservationService->getMesReservations($beneficiaire, ['role' => 'beneficiaire']);
        $this->assertEquals(1, $mesResBenef->total());
        $this->assertEquals($res->id, $mesResBenef->items()[0]->id);

        $mesResInterv = $this->reservationService->getMesReservations($intervenant, ['role' => 'intervenant']);
        $this->assertEquals(1, $mesResInterv->total());
        $this->assertEquals($res->id, $mesResInterv->items()[0]->id);

        // Filtre par statut inexistant
        $vide = $this->reservationService->getMesReservations($beneficiaire, ['statut' => 'realisee']);
        $this->assertEquals(0, $vide->total());
    }
}
