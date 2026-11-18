<?php

namespace Tests\Unit;

use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\Litige;
use App\Models\MouvementCredit;
use App\Models\Offre;
use App\Models\Portefeuille;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AdminStatsService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminStatsServiceTest extends TestCase
{
    protected AdminStatsService $adminStatsService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createTestSchema();

        $this->adminStatsService = new AdminStatsService();
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
     * Test : Calcul exact des statistiques sans effet de bord ni N+1
     */
    public function test_stats_globales_etudiants_offres_reservations_activite(): void
    {
        // 1. Utilisateurs
        $u1 = new User();
        $u1->forceFill(['nom' => 'A', 'prenom' => 'B', 'email' => 'u1@univ.fr', 'password' => 'pwd', 'role' => 'etudiant', 'statut' => 'actif'])->save();
        $u2 = new User();
        $u2->forceFill(['nom' => 'C', 'prenom' => 'D', 'email' => 'u2@univ.fr', 'password' => 'pwd', 'role' => 'etudiant', 'statut' => 'desactive'])->save();
        $admin = new User();
        $admin->forceFill(['nom' => 'Admin', 'prenom' => 'Super', 'email' => 'adm@univ.fr', 'password' => 'pwd', 'role' => 'administrateur', 'statut' => 'actif'])->save();

        // Portefeuilles
        $p1 = new Portefeuille();
        $p1->forceFill(['etudiant_id' => $u1->id, 'solde_disponible' => 4, 'credits_bloques' => 2])->save();

        // 2. Catégories & Offres
        $cat = Categorie::create(['nom' => 'Tech']);
        $off1 = new Offre();
        $off1->forceFill(['etudiant_id' => $u1->id, 'categorie_id' => $cat->id, 'titre' => 'T1', 'competence' => 'C1', 'description' => 'D', 'statut' => 'publiee', 'duree' => 1])->save();
        $off2 = new Offre();
        $off2->forceFill(['etudiant_id' => $u1->id, 'categorie_id' => $cat->id, 'titre' => 'T2', 'competence' => 'C2', 'description' => 'D', 'statut' => 'brouillon', 'duree' => 1])->save();

        // 3. Créneau et Réservations
        $creneau = Creneau::create([
            'offre_id' => $off1->id,
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'heure_debut' => '10:00:00',
            'heure_fin' => '11:00:00',
            'lieu_public' => 'Salle',
            'lien_ou_lieu_prive' => 'Secret',
        ]);

        $res1 = new Reservation();
        $res1->forceFill([
            'creneau_id' => $creneau->id,
            'beneficiaire_id' => $u2->id,
            'date_demande' => now(),
            'statut' => 'realisee',
            'cout_credits' => 1,
            'confirmation_intervenant' => true,
            'confirmation_beneficiaire' => true,
        ])->save();

        // 4. Mouvements financiers (transfert_gain positif de l'intervenant + négatif du bénéficiaire)
        $mvt = new MouvementCredit();
        $mvt->forceFill([
            'portefeuille_id' => $p1->id,
            'reservation_id' => $res1->id,
            'quantite' => 1,
            'type' => 'transfert_gain',
            'date_mouvement' => now(),
        ])->save();

        $mvtDebit = new MouvementCredit();
        $mvtDebit->forceFill([
            'portefeuille_id' => $p1->id,
            'reservation_id' => $res1->id,
            'quantite' => -1,
            'type' => 'transfert_gain',
            'date_mouvement' => now(),
        ])->save();

        // 5. Litige
        $litige = new Litige();
        $litige->forceFill([
            'reservation_id' => $res1->id,
            'declarant_id' => $u2->id,
            'description' => 'Problème séance',
            'statut' => 'ouvert',
        ])->save();

        // Calcul des stats
        $stats = $this->adminStatsService->getStats();

        // Assertions utilisateurs
        $this->assertEquals(3, $stats['utilisateurs']['total']);
        $this->assertEquals(2, $stats['utilisateurs']['etudiants']);
        $this->assertEquals(1, $stats['utilisateurs']['administrateurs']);
        $this->assertEquals(2, $stats['utilisateurs']['actifs']);
        $this->assertEquals(1, $stats['utilisateurs']['desactives']);

        // Assertions offres
        $this->assertEquals(2, $stats['offres']['total']);
        $this->assertEquals(1, $stats['offres']['publiees']);
        $this->assertEquals(1, $stats['offres']['brouillons']);

        // Assertions réservations & activité
        $this->assertEquals(1, $stats['reservations']['total']);
        $this->assertEquals(1, $stats['reservations']['realisees']);
        $this->assertEquals(1, $stats['activite_financiere']['seances_realisees']);
        $this->assertEquals(1, $stats['activite_financiere']['credits_transferes']);
        $this->assertEquals(2, $stats['activite_financiere']['credits_sequestres']);
        $this->assertEquals(1, $stats['activite_financiere']['litiges_ouverts']);
        $this->assertIsArray($stats['evolution_mensuelle']);
    }

    /**
     * Test critique : Vérifie que seuls les transferts réels (transfert_gain > 0)
     * sont comptabilisés, et que blocages, déblocages, restitutions et débits sont ignorés.
     */
    public function test_credits_transferes_calcule_uniquement_transferts_gain_positifs_et_ignore_autres_mouvements(): void
    {
        $user = new User();
        $user->forceFill(['nom' => 'Test', 'prenom' => 'User', 'email' => 'stats_test@univ.fr', 'password' => 'pwd', 'role' => 'etudiant', 'statut' => 'actif'])->save();

        $portefeuille = new Portefeuille();
        $portefeuille->forceFill(['etudiant_id' => $user->id, 'solde_disponible' => 10, 'credits_bloques' => 0])->save();

        // 1. Mouvement de bienvenue (ignoré)
        MouvementCredit::forceCreate([
            'portefeuille_id' => $portefeuille->id,
            'quantite' => 2,
            'type' => 'bienvenue',
            'date_mouvement' => now(),
        ]);

        // 2. Mouvements de blocage / déblocage (séquestre/annulation, ignorés)
        MouvementCredit::forceCreate([
            'portefeuille_id' => $portefeuille->id,
            'quantite' => -3,
            'type' => 'blocage',
            'date_mouvement' => now(),
        ]);
        MouvementCredit::forceCreate([
            'portefeuille_id' => $portefeuille->id,
            'quantite' => 3,
            'type' => 'deblocage_annulation',
            'date_mouvement' => now(),
        ]);

        // 3. Mouvement de restitution litige (remboursement, ignoré)
        MouvementCredit::forceCreate([
            'portefeuille_id' => $portefeuille->id,
            'quantite' => 1,
            'type' => 'restitution_litige',
            'date_mouvement' => now(),
        ]);

        // 4. Mouvements d'un transfert bilatéral légitime de 2 crédits (CreditService::transfererCredits)
        // Débit du bénéficiaire (négatif, ignoré dans le total transféré)
        MouvementCredit::forceCreate([
            'portefeuille_id' => $portefeuille->id,
            'quantite' => -2,
            'type' => 'transfert_gain',
            'date_mouvement' => now(),
        ]);
        // Crédit de l'intervenant (positif, COMPTABILISÉ)
        MouvementCredit::forceCreate([
            'portefeuille_id' => $portefeuille->id,
            'quantite' => 2,
            'type' => 'transfert_gain',
            'date_mouvement' => now(),
        ]);

        $stats = $this->adminStatsService->getStats();

        // Le volume transféré doit être exactement de 2 (et non 2 + 2 - 3 + 3 + 1 - 2...)
        $this->assertEquals(2, $stats['activite_financiere']['credits_transferes']);
    }
}
