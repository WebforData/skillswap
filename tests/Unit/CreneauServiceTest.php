<?php

namespace Tests\Unit;

use App\Exceptions\CompteDesactiveException;
use App\Exceptions\ConflitHoraireException;
use App\Exceptions\CreneauInvalideException;
use App\Exceptions\CreneauOccupeException;
use App\Exceptions\DureeCreneauInvalideException;
use App\Exceptions\OffreNonAutoriseeException;
use App\Exceptions\OffreStatutInvalideException;
use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\Evaluation;
use App\Models\Litige;
use App\Models\Offre;
use App\Models\Portefeuille;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CreditService;
use App\Services\CreneauService;
use App\Services\OffreService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreneauServiceTest extends TestCase
{
    private CreneauService $creneauService;
    private OffreService $offreService;
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
        $this->offreService = new OffreService();
        $this->creneauService = new CreneauService();
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

    private function creerOffrePubliee(User $intervenant, int $duree = 1): Offre
    {
        $cat = Categorie::firstOrCreate(['nom' => 'Développement Informatique']);
        $offre = new Offre();
        $offre->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $cat->id,
            'titre' => 'Initiation Laravel 12 & Architecture',
            'competence' => 'PHP/Laravel',
            'description' => 'Cours structuré sur les transactions et la sécurité SQL.',
            'niveau' => 'debutant',
            'duree' => $duree,
            'mode' => 'presentiel',
            'statut' => 'publiee',
        ]);
        $offre->save();

        return $offre;
    }

    /**
     * Test 1 : Création d'un créneau valide (RG02, RG04)
     */
    public function test_creation_creneau_valide(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 1);

        $dateFuture = Carbon::now()->addDays(2)->toDateString();

        $creneau = $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => $dateFuture,
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Salle B201',
            'lien_ou_lieu_prive' => 'Bâtiment Informatique 2ème étage',
        ]);

        $this->assertEquals($offre->id, $creneau->offre_id);
        $this->assertEquals($dateFuture, $creneau->date->format('Y-m-d'));
        $this->assertEquals('14:00:00', $creneau->heure_debut);
        $this->assertEquals('15:00:00', $creneau->heure_fin);
    }

    /**
     * RG04 — Test 2 : Rejet d'une date ou heure passée
     */
    public function test_rejet_date_ou_heure_passee(): void
    {
        $intervenant = $this->creerUtilisateur('prof_passe@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 1);

        $datePassee = Carbon::now()->subDay()->toDateString();

        $this->expectException(CreneauInvalideException::class);
        $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => $datePassee,
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'lieu_public' => 'Salle A',
            'lien_ou_lieu_prive' => 'Lien secret',
        ]);
    }

    /**
     * RG18 — Test 3 : Rejet si compte intervenant désactivé
     */
    public function test_rejet_compte_desactive(): void
    {
        $intervenantBloque = $this->creerUtilisateur('bloque@univ.fr', 'etudiant', 'desactive');
        $offre = $this->creerOffrePubliee($intervenantBloque, 1);

        $this->expectException(CompteDesactiveException::class);
        $this->creneauService->ajouterCreneau($intervenantBloque, $offre->id, [
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Amphi',
            'lien_ou_lieu_prive' => 'Secret',
        ]);
    }

    /**
     * Test 4 : Rejet si l'offre appartient à un autre étudiant
     */
    public function test_rejet_offre_autre_etudiant(): void
    {
        $intervenant1 = $this->creerUtilisateur('prof1@univ.fr');
        $intervenant2 = $this->creerUtilisateur('prof2@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant1, 1);

        $this->expectException(OffreNonAutoriseeException::class);
        $this->creneauService->ajouterCreneau($intervenant2, $offre->id, [
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Amphi',
            'lien_ou_lieu_prive' => 'Secret',
        ]);
    }

    /**
     * RG04 — Test 5 : Rejet si l'offre n'est pas publiée (ex: brouillon ou archivée)
     */
    public function test_rejet_offre_non_publiee(): void
    {
        $intervenant = $this->creerUtilisateur('prof_brouillon@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 1);
        $offre->statut = 'brouillon';
        $offre->save();

        $this->expectException(OffreStatutInvalideException::class);
        $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Amphi',
            'lien_ou_lieu_prive' => 'Secret',
        ]);
    }

    /**
     * RG02 — Test 6 : Rejet si la durée du créneau ne correspond pas à l'offre (ex: 2h pour offre de 1h)
     */
    public function test_rejet_duree_incorrecte(): void
    {
        $intervenant = $this->creerUtilisateur('prof_duree@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 1); // Durée 1 heure

        $this->expectException(DureeCreneauInvalideException::class);
        $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => Carbon::now()->addDays(3)->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '16:00', // 2 heures au lieu de 1 heure
            'lieu_public' => 'Amphi',
            'lien_ou_lieu_prive' => 'Secret',
        ]);
    }

    /**
     * RG08 — Test 7 : Rejet d'un chevauchement d'horaires pour le même intervenant
     */
    public function test_rejet_chevauchement_horaire_intervenant(): void
    {
        $intervenant = $this->creerUtilisateur('prof_chevauche@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 2); // 2h

        $date = Carbon::now()->addDays(3)->toDateString();

        // 1er créneau : 14:00 - 16:00
        $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => $date,
            'heure_debut' => '14:00',
            'heure_fin' => '16:00',
            'lieu_public' => 'Salle 1',
            'lien_ou_lieu_prive' => 'Secret 1',
        ]);

        // 2ème créneau qui chevauche : 15:00 - 17:00
        $this->expectException(ConflitHoraireException::class);
        $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => $date,
            'heure_debut' => '15:00',
            'heure_fin' => '17:00',
            'lieu_public' => 'Salle 2',
            'lien_ou_lieu_prive' => 'Secret 2',
        ]);
    }

    /**
     * RG08 — Test 8 : Deux séances consécutives sont autorisées
     */
    public function test_autorisation_deux_seances_consecutives(): void
    {
        $intervenant = $this->creerUtilisateur('prof_consecutif@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 1);

        $date = Carbon::now()->addDays(3)->toDateString();

        // Créneau 1 : 14:00 -> 15:00
        $c1 = $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => $date,
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Salle 1',
            'lien_ou_lieu_prive' => 'Secret 1',
        ]);

        // Créneau 2 consécutif : 15:00 -> 16:00 (Autorisé !)
        $c2 = $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => $date,
            'heure_debut' => '15:00',
            'heure_fin' => '16:00',
            'lieu_public' => 'Salle 2',
            'lien_ou_lieu_prive' => 'Secret 2',
        ]);

        $this->assertNotNull($c1->id);
        $this->assertNotNull($c2->id);
    }

    /**
     * Test 9 : Modification d'un créneau libre (sans réservation active)
     */
    public function test_modification_creneau_libre(): void
    {
        $intervenant = $this->creerUtilisateur('prof_modif@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 1);

        $c = $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => Carbon::now()->addDays(3)->toDateString(),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'lieu_public' => 'Salle Initiale',
            'lien_ou_lieu_prive' => 'Secret Initial',
        ]);

        $cModifie = $this->creneauService->modifierCreneau($intervenant, $c->id, [
            'heure_debut' => '11:00',
            'heure_fin' => '12:00',
            'lieu_public' => 'Salle Nouvelle',
        ]);

        $this->assertEquals('11:00:00', $cModifie->heure_debut);
        $this->assertEquals('12:00:00', $cModifie->heure_fin);
        $this->assertEquals('Salle Nouvelle', $cModifie->lieu_public);
    }

    /**
     * RG07 — Test 10 : Rejet de modification d'un créneau attribué (statut acceptee)
     */
    public function test_rejet_modification_creneau_attribue(): void
    {
        $intervenant = $this->creerUtilisateur('prof_attrib@univ.fr');
        $beneficiaire = $this->creerUtilisateur('eleve_attrib@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 1);

        $creneau = $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => Carbon::now()->addDays(4)->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Salle',
            'lien_ou_lieu_prive' => 'Secret',
        ]);

        // Réservation formulée et acceptée
        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        $this->expectException(CreneauOccupeException::class);
        $this->creneauService->modifierCreneau($intervenant, $creneau->id, [
            'heure_debut' => '15:00',
            'heure_fin' => '16:00',
        ]);
    }

    /**
     * RG17 — Test 11 : Modification d'un créneau annule automatiquement les demandes en attente
     */
    public function test_modification_creneau_annule_demandes_en_attente(): void
    {
        $intervenant = $this->creerUtilisateur('prof_annul_attente@univ.fr');
        $beneficiaire = $this->creerUtilisateur('eleve_annul_attente@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 1);

        $creneau = $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => Carbon::now()->addDays(4)->toDateString(),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'lieu_public' => 'Salle',
            'lien_ou_lieu_prive' => 'Secret',
        ]);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->assertEquals('en_attente', $res->fresh()->statut);

        // L'intervenant décale le créneau à 11:00 -> 12:00
        $this->creneauService->modifierCreneau($intervenant, $creneau->id, [
            'heure_debut' => '11:00',
            'heure_fin' => '12:00',
        ]);

        // La demande en attente doit avoir été annulée avec motif explicite
        $this->assertEquals('annulee', $res->fresh()->statut);
        $this->assertStringContainsString('modification', $res->fresh()->motif_refus);
    }

    /**
     * Test 12 : Suppression réussie d'un créneau libre
     */
    public function test_suppression_creneau_libre(): void
    {
        $intervenant = $this->creerUtilisateur('prof_suppr@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 1);

        $c = $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => Carbon::now()->addDays(3)->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Salle',
            'lien_ou_lieu_prive' => 'Secret',
        ]);

        $result = $this->creneauService->supprimerCreneau($intervenant, $c->id);

        $this->assertTrue($result);
        $this->assertNull(Creneau::find($c->id));
    }

    /**
     * RG17 — Test 13 : Suppression d'un créneau annule automatiquement les demandes en attente
     */
    public function test_suppression_creneau_annule_demandes_en_attente(): void
    {
        $intervenant = $this->creerUtilisateur('prof_del_attente@univ.fr');
        $beneficiaire = $this->creerUtilisateur('eleve_del_attente@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 1);

        $creneau = $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => Carbon::now()->addDays(4)->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Salle',
            'lien_ou_lieu_prive' => 'Secret',
        ]);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->assertEquals('en_attente', $res->fresh()->statut);

        $this->assertTrue($this->creneauService->supprimerCreneau($intervenant, $creneau->id));

        // Le créneau est supprimé et les demandes en attente sont purgées pour respecter l'intégrité référentielle
        $this->assertNull(Reservation::find($res->id));
        $this->assertNull(Creneau::find($creneau->id));
    }

    /**
     * RG07 / RG17 — Test 14 : Rejet strict de suppression d'un créneau attribué
     */
    public function test_rejet_suppression_creneau_attribue(): void
    {
        $intervenant = $this->creerUtilisateur('prof_del_attrib@univ.fr');
        $beneficiaire = $this->creerUtilisateur('eleve_del_attrib@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 1);

        $creneau = $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => Carbon::now()->addDays(4)->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Salle',
            'lien_ou_lieu_prive' => 'Secret',
        ]);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        $this->expectException(CreneauOccupeException::class);
        $this->creneauService->supprimerCreneau($intervenant, $creneau->id);
    }

    /**
     * Test 15 : Planning consolidé et vérification de la confidentialité RG19
     */
    public function test_planning_consolide_et_confidentialite_rg19(): void
    {
        $intervenant = $this->creerUtilisateur('prof_planning@univ.fr');
        $beneficiaire = $this->creerUtilisateur('eleve_planning@univ.fr');
        $tiers = $this->creerUtilisateur('tiers_planning@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 1);

        $creneau = $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => Carbon::now()->addDays(5)->toDateString(),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'lieu_public' => 'En ligne',
            'lien_ou_lieu_prive' => 'https://meet.skillswap.edu/salle-ultra-confidentielle',
        ]);

        $res = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $res->id);

        // Planning de l'intervenant
        $planningInter = $this->creneauService->getPlanning($intervenant);
        $this->assertCount(1, $planningInter);
        $this->assertEquals('intervenant', $planningInter[0]['role']);
        $this->assertEquals('https://meet.skillswap.edu/salle-ultra-confidentielle', $planningInter[0]['creneau']['lieu_ou_lien_prive']);

        // Planning du bénéficiaire (accepté -> autorisé RG19)
        $planningBenef = $this->creneauService->getPlanning($beneficiaire);
        $this->assertCount(1, $planningBenef);
        $this->assertEquals('beneficiaire', $planningBenef[0]['role']);
        $this->assertEquals('https://meet.skillswap.edu/salle-ultra-confidentielle', $planningBenef[0]['creneau']['lieu_ou_lien_prive']);

        // Planning d'un tiers sans réservation
        $planningTiers = $this->creneauService->getPlanning($tiers);
        $this->assertCount(0, $planningTiers);
    }

    /**
     * Test 16 : Filtres du planning (a_venir vs historique, role, plage de dates)
     */
    public function test_filtres_du_planning(): void
    {
        $intervenant = $this->creerUtilisateur('prof_filtres@univ.fr');
        $beneficiaire = $this->creerUtilisateur('eleve_filtres@univ.fr');
        $offre = $this->creerOffrePubliee($intervenant, 1);

        $cFutur = $this->creneauService->ajouterCreneau($intervenant, $offre->id, [
            'date' => Carbon::now()->addDays(5)->toDateString(),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'lieu_public' => 'Salle A',
            'lien_ou_lieu_prive' => 'Lien A',
        ]);
        $resFutur = $this->reservationService->demanderReservation($beneficiaire, $cFutur->id);
        $this->reservationService->accepterReservation($intervenant, $resFutur->id);

        // Filtre 'a_venir' -> 1 séance
        $aVenir = $this->creneauService->getPlanning($intervenant, ['a_venir' => true]);
        $this->assertCount(1, $aVenir);

        // Filtre 'historique' -> 0 séance
        $historique = $this->creneauService->getPlanning($intervenant, ['historique' => true]);
        $this->assertCount(0, $historique);

        // Filtre 'role'
        $interRole = $this->creneauService->getPlanning($intervenant, ['role' => 'intervenant']);
        $this->assertCount(1, $interRole);

        $benefRole = $this->creneauService->getPlanning($intervenant, ['role' => 'beneficiaire']);
        $this->assertCount(0, $benefRole);
    }
}
