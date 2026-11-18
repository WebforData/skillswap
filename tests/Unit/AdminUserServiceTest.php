<?php

namespace Tests\Unit;

use App\Exceptions\AdminException;
use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\Offre;
use App\Models\Portefeuille;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AdminUserService;
use App\Services\CreditService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserServiceTest extends TestCase
{
    protected AdminUserService $adminUserService;
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
        $this->adminUserService = new AdminUserService();
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
            $table->string('statut')->default('publiee');
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

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations');
            $table->foreignId('auteur_id')->constrained('users');
            $table->foreignId('cible_id')->constrained('users');
            $table->tinyInteger('note');
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });
    }

    private function creerUtilisateur(string $email, string $role = 'etudiant', string $statut = 'actif', int $solde = 5): User
    {
        $user = new User();
        $user->forceFill([
            'nom' => 'Nom' . substr(md5($email), 0, 4),
            'prenom' => 'Prenom',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
            'statut' => $statut,
            'filiere' => 'Informatique',
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

    /**
     * Test 1 : Liste paginée des utilisateurs avec filtres et recherche
     */
    public function test_lister_utilisateurs_avec_pagination_et_recherche(): void
    {
        $this->creerUtilisateur('alice@univ.fr', 'etudiant', 'actif');
        $this->creerUtilisateur('bob@univ.fr', 'etudiant', 'desactive');
        $admin = $this->creerUtilisateur('admin@univ.fr', 'administrateur', 'actif');

        // Tous les utilisateurs
        $tous = $this->adminUserService->listerUtilisateurs();
        $this->assertEquals(3, $tous->total());

        // Filtre rôle étudiant
        $etudiants = $this->adminUserService->listerUtilisateurs(['role' => 'etudiant']);
        $this->assertEquals(2, $etudiants->total());

        // Filtre statut désactivé
        $desactives = $this->adminUserService->listerUtilisateurs(['statut' => 'desactive']);
        $this->assertEquals(1, $desactives->total());

        // Recherche par email
        $recherche = $this->adminUserService->listerUtilisateurs(['q' => 'alice']);
        $this->assertEquals(1, $recherche->total());
        $this->assertEquals('alice@univ.fr', $recherche->items()[0]->email);
    }

    /**
     * Test 2 : Consultation détaillée du profil utilisateur
     */
    public function test_get_utilisateur_details(): void
    {
        $user = $this->creerUtilisateur('charlie@univ.fr', 'etudiant', 'actif', 8);

        $details = $this->adminUserService->getUtilisateur($user->id);

        $this->assertEquals($user->id, $details->id);
        $this->assertEquals(8, $details->portefeuille->solde_disponible);
    }

    /**
     * RG18 — Test 3 : Désactivation d'un compte annule les demandes en attente,
     * préserve les réservations acceptées et protège les crédits bloqués.
     */
    public function test_desactivation_compte_etudiant_annule_demandes_en_attente_rg18(): void
    {
        $admin = $this->creerUtilisateur('admin_desact@univ.fr', 'administrateur', 'actif');
        $etudiantCible = $this->creerUtilisateur('cible@univ.fr', 'etudiant', 'actif', 5);
        $autreEtudiant = $this->creerUtilisateur('autre@univ.fr', 'etudiant', 'actif', 5);

        $cat = Categorie::firstOrCreate(['nom' => 'Langues']);

        // 1. Offre de la cible
        $offreCible = new Offre();
        $offreCible->forceFill([
            'etudiant_id' => $etudiantCible->id,
            'categorie_id' => $cat->id,
            'titre' => 'Anglais C1',
            'competence' => 'Anglais',
            'description' => 'Cours d\'anglais',
            'statut' => 'publiee',
            'duree' => 1,
        ])->save();
        $creneauCible1 = Creneau::create([
            'offre_id' => $offreCible->id,
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'heure_debut' => '10:00:00',
            'heure_fin' => '11:00:00',
            'lieu_public' => 'Salle 1',
            'lien_ou_lieu_prive' => 'Secret 1',
        ]);
        $creneauCible2 = Creneau::create([
            'offre_id' => $offreCible->id,
            'date' => Carbon::now()->addDays(3)->toDateString(),
            'heure_debut' => '14:00:00',
            'heure_fin' => '15:00:00',
            'lieu_public' => 'Salle 2',
            'lien_ou_lieu_prive' => 'Secret 2',
        ]);

        // Demande 1 reçue par la cible : reste en attente
        $demandeRecueEnAttente = $this->reservationService->demanderReservation($autreEtudiant, $creneauCible1->id);

        // Demande 2 reçue par la cible : acceptée ! (les crédits de $autreEtudiant sont bloqués)
        $demandeRecueAcceptee = $this->reservationService->demanderReservation($autreEtudiant, $creneauCible2->id);
        $this->reservationService->accepterReservation($etudiantCible, $demandeRecueAcceptee->id);

        // 2. Offre de l'autre étudiant, cible formule une demande acceptée
        $offreAutre = new Offre();
        $offreAutre->forceFill([
            'etudiant_id' => $autreEtudiant->id,
            'categorie_id' => $cat->id,
            'titre' => 'Espagnol',
            'competence' => 'Espagnol',
            'description' => 'Cours',
            'statut' => 'publiee',
            'duree' => 1,
        ])->save();

        $creneauAutre = Creneau::create([
            'offre_id' => $offreAutre->id,
            'date' => Carbon::now()->addDays(4)->toDateString(),
            'heure_debut' => '16:00:00',
            'heure_fin' => '17:00:00',
            'lieu_public' => 'Salle 3',
            'lien_ou_lieu_prive' => 'Secret 3',
        ]);

        $demandeEnvoyeeAcceptee = $this->reservationService->demanderReservation($etudiantCible, $creneauAutre->id);
        $this->reservationService->accepterReservation($autreEtudiant, $demandeEnvoyeeAcceptee->id);

        // Vérifier les crédits bloqués de la cible avant désactivation
        $portefeuilleAvant = Portefeuille::where('etudiant_id', $etudiantCible->id)->first();
        $this->assertEquals(1, $portefeuilleAvant->credits_bloques);
        $this->assertEquals(4, $portefeuilleAvant->solde_disponible);

        // --- EXÉCUTION DE LA DÉSACTIVATION PAR L'ADMINISTRATEUR ---
        $resUser = $this->adminUserService->desactiverCompte($admin, $etudiantCible->id);

        $this->assertEquals('desactive', $resUser->statut);

        // 1. La demande en attente reçue doit être annulée avec motif explicite
        $this->assertEquals('annulee', $demandeRecueEnAttente->fresh()->statut);
        $this->assertStringContainsString('désactivé', $demandeRecueEnAttente->fresh()->motif_refus);

        // 2. Les réservations acceptées doivent rester INTACTES (préservation des engagements)
        $this->assertEquals('acceptee', $demandeRecueAcceptee->fresh()->statut);
        $this->assertEquals('acceptee', $demandeEnvoyeeAcceptee->fresh()->statut);

        // 3. Les crédits bloqués doivent être STRICTEMENT PRÉSERVÉS (aucun mouvement fantôme)
        $portefeuilleApres = Portefeuille::where('etudiant_id', $etudiantCible->id)->first();
        $this->assertEquals(1, $portefeuilleApres->credits_bloques);
        $this->assertEquals(4, $portefeuilleApres->solde_disponible);
    }

    /**
     * Test 4 : Interdiction à un administrateur de désactiver son propre compte
     */
    public function test_interdiction_auto_desactivation_administrateur(): void
    {
        $admin = $this->creerUtilisateur('superadmin@univ.fr', 'administrateur', 'actif');

        $this->expectException(AdminException::class);
        $this->adminUserService->desactiverCompte($admin, $admin->id);
    }

    /**
     * Test 5 : Réactivation réussie d'un compte
     */
    public function test_reactivation_compte_etudiant(): void
    {
        $admin = $this->creerUtilisateur('admin_react@univ.fr', 'administrateur', 'actif');
        $cible = $this->creerUtilisateur('cible_react@univ.fr', 'etudiant', 'desactive');

        $userReactif = $this->adminUserService->activerCompte($admin, $cible->id);

        $this->assertEquals('actif', $userReactif->statut);
    }
}
