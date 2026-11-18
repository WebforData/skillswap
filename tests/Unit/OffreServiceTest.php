<?php

namespace Tests\Unit;

use App\Exceptions\CompteDesactiveException;
use App\Exceptions\DureeOffreIncompatibleException;
use App\Exceptions\OffreNonAutoriseeException;
use App\Exceptions\OffreStatutInvalideException;
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
use Tests\TestCase;

class OffreServiceTest extends TestCase
{
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

    /**
     * Test 1 : Création d'une offre par un étudiant actif (brouillon par défaut)
     */
    public function test_creation_offre_par_etudiant_actif(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant@univ.fr');
        $categorie = Categorie::create(['nom' => 'Informatique']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Initiation au Machine Learning',
            'competence' => 'Python/Data',
            'description' => 'Découverte approfondie des modèles de régression et classification.',
            'niveau' => 'intermediaire',
            'duree' => 2,
            'mode' => 'distanciel',
        ]);

        $this->assertEquals($intervenant->id, $offre->etudiant_id);
        $this->assertEquals('Initiation au Machine Learning', $offre->titre);
        $this->assertEquals(2, $offre->duree);
        $this->assertEquals('brouillon', $offre->statut);
    }

    /**
     * Test 2 : Création d'une offre avec statut publiée explicite
     */
    public function test_creation_offre_avec_statut_publiee(): void
    {
        $intervenant = $this->creerUtilisateur('intervenant_pub@univ.fr');
        $categorie = Categorie::create(['nom' => 'Mathématiques']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Algèbre Linéaire & Matrices',
            'competence' => 'Maths',
            'description' => 'Espaces vectoriels et diagonalisation.',
            'niveau' => 'debutant',
            'duree' => 1,
            'mode' => 'presentiel',
            'statut' => 'publiee',
        ]);

        $this->assertEquals('publiee', $offre->statut);
    }

    /**
     * Test 3 : Rejet de la création par un compte désactivé (RG18)
     */
    public function test_refus_creation_offre_compte_desactive(): void
    {
        $intervenantBloque = $this->creerUtilisateur('bloque@univ.fr', 'etudiant', 'desactive');
        $categorie = Categorie::create(['nom' => 'Langues']);

        $this->expectException(CompteDesactiveException::class);
        $this->offreService->creerOffre($intervenantBloque, [
            'categorie_id' => $categorie->id,
            'titre' => 'Anglais Oral & Fluency',
            'competence' => 'Anglais',
            'description' => 'Pratique orale pour entretiens et TOEIC.',
        ]);
    }

    /**
     * Test 4 : Modification de l'offre par son propriétaire
     */
    public function test_modification_offre_par_proprietaire(): void
    {
        $intervenant = $this->creerUtilisateur('auteur@univ.fr');
        $categorie = Categorie::create(['nom' => 'Design']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Titre initial',
            'competence' => 'Figma',
            'description' => 'Description initiale détaillée d au moins 20 caractères.',
            'duree' => 1,
        ]);

        $updated = $this->offreService->modifierOffre($intervenant, $offre->id, [
            'titre' => 'Nouveau Titre Perfectionné',
            'mode' => 'distanciel',
        ]);

        $this->assertEquals('Nouveau Titre Perfectionné', $updated->titre);
        $this->assertEquals('distanciel', $updated->mode);
    }

    /**
     * Test 5 : Rejet de modification par un étudiant tiers
     */
    public function test_refus_modification_offre_par_tiers(): void
    {
        $intervenant = $this->creerUtilisateur('proprio@univ.fr');
        $tiers = $this->creerUtilisateur('imposteur@univ.fr');
        $categorie = Categorie::create(['nom' => 'Gestion']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Gestion de Projet Agile',
            'competence' => 'Scrum',
            'description' => 'Description initiale détaillée d au moins 20 caractères.',
        ]);

        $this->expectException(OffreNonAutoriseeException::class);
        $this->offreService->modifierOffre($tiers, $offre->id, [
            'titre' => 'Piratage de titre',
        ]);
    }

    /**
     * Test 6 : Publication et repassage en brouillon
     */
    public function test_publication_et_repassage_en_brouillon(): void
    {
        $intervenant = $this->creerUtilisateur('pub_brouillon@univ.fr');
        $categorie = Categorie::create(['nom' => 'Réseaux']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Configuration Cisco & Routing',
            'competence' => 'Réseaux',
            'description' => 'Protocoles OSPF, BGP et sous-réseaux IP.',
        ]);

        $this->assertEquals('brouillon', $offre->statut);

        // Publication
        $publiee = $this->offreService->publierOffre($intervenant, $offre->id);
        $this->assertEquals('publiee', $publiee->statut);

        // Brouillon
        $brouillon = $this->offreService->passerEnBrouillon($intervenant, $offre->id);
        $this->assertEquals('brouillon', $brouillon->statut);
    }

    /**
     * RG02 — Test 7 : Rejet de modification de la durée si des créneaux incompatibles existent
     */
    public function test_refus_modification_duree_si_creneaux_incompatibles(): void
    {
        $intervenant = $this->creerUtilisateur('duree@univ.fr');
        $categorie = Categorie::create(['nom' => 'Web']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Formation Vue.js 3',
            'competence' => 'JavaScript',
            'description' => 'Apprentissage de Vue et de la Composition API.',
            'duree' => 1, // Durée initiale 1h
        ]);

        // Création d'un créneau de 1h conforme (10:00 -> 11:00)
        $creneau = new Creneau();
        $creneau->forceFill([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'heure_debut' => '10:00:00',
            'heure_fin' => '11:00:00',
            'lieu_public' => 'En ligne',
            'lien_ou_lieu_prive' => 'https://meet.skillswap.edu/vue',
        ]);
        $creneau->save();

        // Tenter de passer l'offre à 2h alors que le créneau existant dure 1h
        $this->expectException(DureeOffreIncompatibleException::class);
        $this->offreService->modifierOffre($intervenant, $offre->id, [
            'duree' => 2,
        ]);
    }

    /**
     * RG17 — Test 8 : Archivage d'une offre annule automatiquement les réservations en attente
     */
    public function test_rg17_archivage_offre_annule_demandes_en_attente(): void
    {
        $intervenant = $this->creerUtilisateur('prof@univ.fr');
        $beneficiaire1 = $this->creerUtilisateur('eleve1@univ.fr');
        $beneficiaire2 = $this->creerUtilisateur('eleve2@univ.fr');
        $categorie = Categorie::create(['nom' => 'IA']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Deep Learning & PyTorch',
            'competence' => 'IA',
            'description' => 'Réseaux de neurones convolutifs et Transformers.',
            'duree' => 1,
            'statut' => 'publiee',
        ]);

        $creneau = new Creneau();
        $creneau->forceFill([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(3)->toDateString(),
            'heure_debut' => '14:00:00',
            'heure_fin' => '15:00:00',
            'lieu_public' => 'Labo IA',
            'lien_ou_lieu_prive' => 'Salle 302',
        ]);
        $creneau->save();

        // 2 demandes en attente formulées sur ce créneau
        $res1 = $this->reservationService->demanderReservation($beneficiaire1, $creneau->id);
        $res2 = $this->reservationService->demanderReservation($beneficiaire2, $creneau->id);

        $this->assertEquals('en_attente', $res1->fresh()->statut);
        $this->assertEquals('en_attente', $res2->fresh()->statut);

        // Archivage de l'offre par l'intervenant (RG17)
        $archivee = $this->offreService->archiverOffre($intervenant, $offre->id);

        $this->assertEquals('archivee', $archivee->statut);

        // RG17 : Les deux demandes doivent être passées à 'annulee' avec motif explicite
        $this->assertEquals('annulee', $res1->fresh()->statut);
        $this->assertStringContainsString('RG17', $res1->fresh()->motif_refus);
        $this->assertEquals('annulee', $res2->fresh()->statut);
        $this->assertStringContainsString('RG17', $res2->fresh()->motif_refus);
    }

    /**
     * RG17 — Test 9 : L'archivage d'une offre préserve les réservations acceptées et les crédits bloqués
     */
    public function test_rg17_archivage_offre_preserve_reservations_acceptees_et_credits_bloques(): void
    {
        $intervenant = $this->creerUtilisateur('prof_accept@univ.fr');
        $beneficiaire = $this->creerUtilisateur('eleve_accept@univ.fr', 'etudiant', 'actif', 5);
        $categorie = Categorie::create(['nom' => 'Cloud']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Docker & Kubernetes',
            'competence' => 'DevOps',
            'description' => 'Conteneurisation et orchestration de microservices.',
            'duree' => 2,
            'statut' => 'publiee',
        ]);

        $creneau = new Creneau();
        $creneau->forceFill([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(4)->toDateString(),
            'heure_debut' => '09:00:00',
            'heure_fin' => '11:00:00',
            'lieu_public' => 'En ligne',
            'lien_ou_lieu_prive' => 'https://meet.skillswap.edu/docker',
        ]);
        $creneau->save();

        // Demande et acceptation -> crédits bloqués (2 crédits)
        $reservation = $this->reservationService->demanderReservation($beneficiaire, $creneau->id);
        $this->reservationService->accepterReservation($intervenant, $reservation->id);

        $this->assertEquals('acceptee', $reservation->fresh()->statut);

        // Vérification des crédits bloqués
        $walletBenef = Portefeuille::where('etudiant_id', $beneficiaire->id)->first();
        $this->assertEquals(2, $walletBenef->credits_bloques);
        $this->assertEquals(3, $walletBenef->solde_disponible);

        // Archivage de l'offre (RG17)
        $this->offreService->archiverOffre($intervenant, $offre->id);

        // RG17 : La réservation acceptée DOIT DEMEURER 'acceptee'
        $this->assertEquals('acceptee', $reservation->fresh()->statut);

        // Les crédits bloqués NE DOIVENT PAS être perdus ni modifiés
        $walletBenefFresh = Portefeuille::where('etudiant_id', $beneficiaire->id)->first();
        $this->assertEquals(2, $walletBenefFresh->credits_bloques);
        $this->assertEquals(3, $walletBenefFresh->solde_disponible);
    }

    /**
     * RG17 — Test 10 : Refus de modification ou réactivation d'une offre archivée
     */
    public function test_refus_modification_offre_archivee(): void
    {
        $intervenant = $this->creerUtilisateur('archiviste@univ.fr');
        $categorie = Categorie::create(['nom' => 'Sécurité']);

        $offre = $this->offreService->creerOffre($intervenant, [
            'categorie_id' => $categorie->id,
            'titre' => 'Cryptographie Moderne',
            'competence' => 'Sécurité',
            'description' => 'Chiffrement symétrique, asymétrique et RSA.',
            'statut' => 'publiee',
        ]);

        $this->offreService->archiverOffre($intervenant, $offre->id);

        // Tenter de modifier
        $this->expectException(OffreStatutInvalideException::class);
        $this->offreService->modifierOffre($intervenant, $offre->id, [
            'titre' => 'Tentative sur offre archivée',
        ]);
    }

    /**
     * RG04 — Test 11 : Recherche publique avec filtres combinés et exclusion des brouillons
     */
    public function test_recherche_offres_filtres_combines(): void
    {
        $intervenantActif = $this->creerUtilisateur('actif@univ.fr');
        $intervenantBloque = $this->creerUtilisateur('inactif@univ.fr', 'etudiant', 'desactive');

        $catWeb = Categorie::create(['nom' => 'Développement Web']);
        $catData = Categorie::create(['nom' => 'Data Science']);

        // Offre 1 : Web, 1h, débutant, présentiel, publiée, compte actif (DOIT SORTIR)
        $this->offreService->creerOffre($intervenantActif, [
            'categorie_id' => $catWeb->id,
            'titre' => 'Cours de React 19 pour débutant',
            'competence' => 'React/Frontend',
            'description' => 'Apprenez les bases de React et des composants modernes.',
            'niveau' => 'debutant',
            'duree' => 1,
            'mode' => 'presentiel',
            'statut' => 'publiee',
        ]);

        // Offre 2 : Web, 2h, avancé, distanciel, publiée, compte actif (Filtre durée l'exclut)
        $this->offreService->creerOffre($intervenantActif, [
            'categorie_id' => $catWeb->id,
            'titre' => 'Architecture React & Next.js Avancé',
            'competence' => 'Next.js',
            'description' => 'Server components et rendu hybride SSR.',
            'niveau' => 'avance',
            'duree' => 2,
            'mode' => 'distanciel',
            'statut' => 'publiee',
        ]);

        // Offre 3 : Web, brouillon (Ne doit JAMAIS sortir dans la recherche publique)
        $this->offreService->creerOffre($intervenantActif, [
            'categorie_id' => $catWeb->id,
            'titre' => 'React en brouillon non terminé',
            'competence' => 'React',
            'description' => 'Offre en cours de rédaction.',
            'duree' => 1,
            'statut' => 'brouillon',
        ]);

        // Offre 4 : Web, publiée mais compte intervenant DÉSACTIVÉ après création (RG04/RG18 : Exclue)
        $intervenantQuiSeraBloque = $this->creerUtilisateur('futur_bloque@univ.fr', 'etudiant', 'actif');
        $this->offreService->creerOffre($intervenantQuiSeraBloque, [
            'categorie_id' => $catWeb->id,
            'titre' => 'React par intervenant suspendu',
            'competence' => 'React',
            'description' => 'Compte bloqué ultérieurement pour manquement aux règles.',
            'statut' => 'publiee',
        ]);
        $intervenantQuiSeraBloque->statut = 'desactive';
        $intervenantQuiSeraBloque->save();

        // Recherche avec filtres combinés : q='React', categorie=catWeb, duree=1, niveau=debutant
        $resultats = $this->offreService->rechercherOffres([
            'q' => 'React',
            'categorie_id' => $catWeb->id,
            'duree' => 1,
            'niveau' => 'debutant',
        ]);

        $this->assertEquals(1, $resultats->total());
        $this->assertEquals('Cours de React 19 pour débutant', $resultats->items()[0]->titre);
    }
}
