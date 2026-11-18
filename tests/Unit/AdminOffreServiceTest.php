<?php

namespace Tests\Unit;

use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\Offre;
use App\Models\Portefeuille;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AdminOffreService;
use App\Services\CreditService;
use App\Services\OffreService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminOffreServiceTest extends TestCase
{
    protected AdminOffreService $adminOffreService;
    protected OffreService $offreService;
    protected ReservationService $reservationService;
    protected CreditService $creditService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createTestSchema();

        $this->creditService = new CreditService();
        $this->reservationService = new ReservationService($this->creditService);
        $this->offreService = new OffreService();
        $this->adminOffreService = new AdminOffreService($this->offreService);
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
    }

    private function creerEtudiant(string $email, int $solde = 5): User
    {
        $u = new User();
        $u->forceFill([
            'nom' => 'Nom' . substr(md5($email), 0, 4),
            'prenom' => 'Prenom',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'etudiant',
            'statut' => 'actif',
        ]);
        $u->save();

        $p = new Portefeuille();
        $p->forceFill([
            'etudiant_id' => $u->id,
            'solde_disponible' => $solde,
            'credits_bloques' => 0,
        ]);
        $p->save();

        return $u;
    }

    /**
     * Test 1 : Liste des offres avec filtres et recherche
     */
    public function test_lister_offres_avec_recherche_et_filtres(): void
    {
        $etudiant = $this->creerEtudiant('prof_offres@univ.fr');
        $cat = Categorie::create(['nom' => 'Informatique']);

        $offre1 = new Offre();
        $offre1->forceFill([
            'etudiant_id' => $etudiant->id,
            'categorie_id' => $cat->id,
            'titre' => 'React et TypeScript',
            'competence' => 'React',
            'description' => 'Développement frontend',
            'statut' => 'publiee',
            'duree' => 1,
        ])->save();

        $offre2 = new Offre();
        $offre2->forceFill([
            'etudiant_id' => $etudiant->id,
            'categorie_id' => $cat->id,
            'titre' => 'Bases SQL',
            'competence' => 'MySQL',
            'description' => 'Bases de données relationnelles',
            'statut' => 'brouillon',
            'duree' => 1,
        ])->save();

        // Total
        $toutes = $this->adminOffreService->listerOffres();
        $this->assertEquals(2, $toutes->total());

        // Filtre publiées
        $publiees = $this->adminOffreService->listerOffres(['statut' => 'publiee']);
        $this->assertEquals(1, $publiees->total());

        // Recherche par compétence
        $recherche = $this->adminOffreService->listerOffres(['q' => 'React']);
        $this->assertEquals(1, $recherche->total());
        $this->assertEquals('React et TypeScript', $recherche->items()[0]->titre);
    }

    /**
     * Test 2 : Consultation détaillée pour modération
     */
    public function test_get_offre_details_pour_moderation(): void
    {
        $etudiant = $this->creerEtudiant('prof_det@univ.fr');
        $cat = Categorie::create(['nom' => 'Physique']);

        $offre = new Offre();
        $offre->forceFill([
            'etudiant_id' => $etudiant->id,
            'categorie_id' => $cat->id,
            'titre' => 'Mécanique Quantique',
            'competence' => 'Physique',
            'description' => 'Cours avancé',
            'statut' => 'publiee',
            'duree' => 2,
        ])->save();

        $details = $this->adminOffreService->getOffre($offre->id);

        $this->assertEquals($offre->id, $details->id);
        $this->assertEquals('Physique', $details->categorie->nom);
        $this->assertEquals($etudiant->id, $details->intervenant->id);
    }

    /**
     * RG17 — Test 3 : Archivage d'une offre par l'administrateur
     */
    public function test_archiver_offre_par_administrateur_preserve_reservations_et_credits_rg17(): void
    {
        $admin = new User();
        $admin->forceFill([
            'nom' => 'Admin',
            'prenom' => 'Principal',
            'email' => 'admin_arch@univ.fr',
            'password' => Hash::make('password'),
            'role' => 'administrateur',
            'statut' => 'actif',
        ])->save();

        $intervenant = $this->creerEtudiant('prof_arch@univ.fr');
        $beneficiaire1 = $this->creerEtudiant('eleve_arch1@univ.fr', 5);
        $beneficiaire2 = $this->creerEtudiant('eleve_arch2@univ.fr', 5);

        $cat = Categorie::create(['nom' => 'Mathématiques']);
        $offre = new Offre();
        $offre->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $cat->id,
            'titre' => 'Algèbre Linéaire',
            'competence' => 'Maths',
            'description' => 'Cours',
            'statut' => 'publiee',
            'duree' => 1,
        ])->save();

        $creneau1 = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'heure_debut' => '10:00:00',
            'heure_fin' => '11:00:00',
            'lieu_public' => 'Salle 1',
            'lien_ou_lieu_prive' => 'Secret 1',
        ]);

        $creneau2 = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(3)->toDateString(),
            'heure_debut' => '14:00:00',
            'heure_fin' => '15:00:00',
            'lieu_public' => 'Salle 2',
            'lien_ou_lieu_prive' => 'Secret 2',
        ]);

        // Res 1 : en attente
        $demandeEnAttente = $this->reservationService->demanderReservation($beneficiaire1, $creneau1->id);

        // Res 2 : acceptée (crédits bloqués)
        $demandeAcceptee = $this->reservationService->demanderReservation($beneficiaire2, $creneau2->id);
        $this->reservationService->accepterReservation($intervenant, $demandeAcceptee->id);

        $portefeuilleBenef2 = Portefeuille::where('etudiant_id', $beneficiaire2->id)->first();
        $this->assertEquals(1, $portefeuilleBenef2->credits_bloques);

        // --- Archivage par l'admin ---
        $offreArchivee = $this->adminOffreService->archiverOffre($admin, $offre->id);

        $this->assertEquals('archivee', $offreArchivee->statut);

        // Demande en attente annulée (RG17)
        $this->assertEquals('annulee', $demandeEnAttente->fresh()->statut);
        $this->assertStringContainsString('archivage', $demandeEnAttente->fresh()->motif_refus);

        // Réservation acceptée préservée (RG17)
        $this->assertEquals('acceptee', $demandeAcceptee->fresh()->statut);

        // Crédits bloqués préservés
        $this->assertEquals(1, $portefeuilleBenef2->fresh()->credits_bloques);
    }
}
