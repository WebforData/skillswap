<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\Litige;
use App\Models\MouvementCredit;
use App\Models\Offre;
use App\Models\Portefeuille;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createTestSchema();
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

    private function creerAdmin(string $email = 'admin@univ.fr'): User
    {
        $admin = new User();
        $admin->forceFill([
            'nom' => 'Admin',
            'prenom' => 'Platform',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'administrateur',
            'statut' => 'actif',
        ]);
        $admin->save();

        return $admin;
    }

    private function creerEtudiant(string $email = 'student@univ.fr', string $statut = 'actif'): User
    {
        $user = new User();
        $user->forceFill([
            'nom' => 'Student',
            'prenom' => 'User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'etudiant',
            'statut' => $statut,
        ]);
        $user->save();

        $portefeuille = new Portefeuille();
        $portefeuille->forceFill([
            'etudiant_id' => $user->id,
            'solde_disponible' => 5,
            'credits_bloques' => 0,
        ]);
        $portefeuille->save();

        return $user;
    }

    /**
     * Test 1 : Accès administrateur autorisé (HTTP 200)
     */
    public function test_admin_acces_autorise_200(): void
    {
        $admin = $this->creerAdmin();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(200);
    }

    /**
     * Test 2 : Accès étudiant rejeté avec HTTP 403
     */
    public function test_etudiant_recoit_403_sur_endpoints_admin(): void
    {
        $etudiant = $this->creerEtudiant();
        Sanctum::actingAs($etudiant);

        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(403);

        $responseStats = $this->getJson('/api/admin/stats');
        $responseStats->assertStatus(403);
    }

    /**
     * Test 3 : Visiteur non authentifié rejeté avec HTTP 401
     */
    public function test_visiteur_non_authentifie_recoit_401(): void
    {
        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(401);

        $responseStats = $this->getJson('/api/admin/stats');
        $responseStats->assertStatus(401);
    }

    /**
     * Test 4 : GET /api/admin/users - Liste paginée et filtres
     */
    public function test_api_admin_users_index_et_filtres(): void
    {
        $admin = $this->creerAdmin();
        $this->creerEtudiant('etudiant1@univ.fr', 'actif');
        $this->creerEtudiant('etudiant2@univ.fr', 'desactive');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users?statut=actif');
        $response->assertStatus(200)
            ->assertJsonPath('total', 2); // 1 admin actif + 1 étudiant actif

        $responseDesact = $this->getJson('/api/admin/users?statut=desactive');
        $responseDesact->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    /**
     * Test 5 : GET /api/admin/users/{id} - Profil détaillé
     */
    public function test_api_admin_users_show(): void
    {
        $admin = $this->creerAdmin();
        $etudiant = $this->creerEtudiant('profil@univ.fr');

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/users/{$etudiant->id}");
        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'profil@univ.fr')
            ->assertJsonPath('user.portefeuille.solde_disponible', 5);
    }

    /**
     * Test 6 : PATCH /api/admin/users/{id}/desactiver - RG18
     */
    public function test_api_admin_users_desactiver_rg18(): void
    {
        $admin = $this->creerAdmin();
        $etudiant = $this->creerEtudiant('cible_api@univ.fr');

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/users/{$etudiant->id}/desactiver");
        $response->assertStatus(200)
            ->assertJsonPath('user.statut', 'desactive');

        $this->assertEquals('desactive', $etudiant->fresh()->statut);
    }

    /**
     * Test 7 : PATCH /api/admin/users/{id}/desactiver - Rejet auto-désactivation admin
     */
    public function test_api_admin_users_desactiver_rejet_auto_desactivation(): void
    {
        $admin = $this->creerAdmin();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/users/{$admin->id}/desactiver");
        $response->assertStatus(403);
    }

    /**
     * Test 8 : PATCH /api/admin/users/{id}/activer - Réactivation
     */
    public function test_api_admin_users_activer(): void
    {
        $admin = $this->creerAdmin();
        $etudiant = $this->creerEtudiant('react_api@univ.fr', 'desactive');

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/users/{$etudiant->id}/activer");
        $response->assertStatus(200)
            ->assertJsonPath('user.statut', 'actif');

        $this->assertEquals('actif', $etudiant->fresh()->statut);
    }

    /**
     * Test 9 : CRUD Catégories (/api/admin/categories)
     */
    public function test_api_admin_categories_crud_complet(): void
    {
        $admin = $this->creerAdmin();
        Sanctum::actingAs($admin);

        // 1. Store
        $respStore = $this->postJson('/api/admin/categories', ['nom' => 'CyberSécurité']);
        $respStore->assertStatus(201)
            ->assertJsonPath('categorie.nom', 'CyberSécurité');

        $catId = $respStore->json('categorie.id');

        // 2. Index
        $respIndex = $this->getJson('/api/admin/categories');
        $respIndex->assertStatus(200)
            ->assertJsonPath('total', 1);

        // 3. Update
        $respUpdate = $this->putJson("/api/admin/categories/{$catId}", ['nom' => 'Sécurité Informatique']);
        $respUpdate->assertStatus(200)
            ->assertJsonPath('categorie.nom', 'Sécurité Informatique');

        // 4. Destroy
        $respDelete = $this->deleteJson("/api/admin/categories/{$catId}");
        $respDelete->assertStatus(200);
        $this->assertDatabaseMissing('categories', ['id' => $catId]);
    }

    /**
     * Test 10 : Rejet suppression catégorie utilisée (HTTP 409)
     */
    public function test_api_admin_categories_delete_rejet_si_offres_rattachees_409(): void
    {
        $admin = $this->creerAdmin();
        $etudiant = $this->creerEtudiant('prof_cat@univ.fr');
        $cat = Categorie::create(['nom' => 'Langues']);

        $offre = new Offre();
        $offre->forceFill([
            'etudiant_id' => $etudiant->id,
            'categorie_id' => $cat->id,
            'titre' => 'Anglais',
            'competence' => 'Anglais',
            'description' => 'Cours',
            'duree' => 1,
            'statut' => 'publiee',
        ])->save();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/admin/categories/{$cat->id}");
        $response->assertStatus(409);
        $this->assertDatabaseHas('categories', ['id' => $cat->id]);
    }

    /**
     * Test 11 : Offres (/api/admin/offres) - Listing et archivage (RG17)
     */
    public function test_api_admin_offres_index_et_archiver_rg17(): void
    {
        $admin = $this->creerAdmin();
        $etudiant = $this->creerEtudiant('prof_off@univ.fr');
        $cat = Categorie::create(['nom' => 'Arts']);

        $offre = new Offre();
        $offre->forceFill([
            'etudiant_id' => $etudiant->id,
            'categorie_id' => $cat->id,
            'titre' => 'Photographie',
            'competence' => 'Photo',
            'description' => 'Cours photo reflex',
            'statut' => 'publiee',
            'duree' => 1,
        ])->save();

        Sanctum::actingAs($admin);

        // Index
        $respIndex = $this->getJson('/api/admin/offres?statut=publiee');
        $respIndex->assertStatus(200)
            ->assertJsonPath('total', 1);

        // Show
        $respShow = $this->getJson("/api/admin/offres/{$offre->id}");
        $respShow->assertStatus(200)
            ->assertJsonPath('offre.titre', 'Photographie');

        // Archiver
        $respArch = $this->patchJson("/api/admin/offres/{$offre->id}/archiver");
        $respArch->assertStatus(200)
            ->assertJsonPath('offre.statut', 'archivee');

        $this->assertEquals('archivee', $offre->fresh()->statut);
    }

    /**
     * Test 12 : GET /api/admin/stats - Récupération complète des indicateurs
     */
    public function test_api_admin_stats_index_succes(): void
    {
        $admin = $this->creerAdmin();
        $this->creerEtudiant('etud_stat@univ.fr');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/stats');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => [
                    'utilisateurs' => ['total', 'etudiants', 'administrateurs', 'actifs', 'desactives'],
                    'offres' => ['total', 'publiees', 'brouillons', 'archivees'],
                    'reservations' => ['total', 'en_attente', 'acceptees', 'realisees', 'annulees', 'en_litige', 'refusees'],
                    'activite_financiere' => ['seances_realisees', 'credits_transferes', 'credits_sequestres', 'litiges_ouverts', 'litiges_resolus'],
                    'evolution_mensuelle',
                ],
            ])
            ->assertJsonPath('stats.utilisateurs.administrateurs', 1)
            ->assertJsonPath('stats.utilisateurs.etudiants', 1);
    }
}
