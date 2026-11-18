<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\Litige;
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

class ApiAuthorizationTest extends TestCase
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
            $table->dateTime('date_resolution')->nullable();
            $table->timestamps();
        });
    }

    private function createUser(array $attributes = []): User
    {
        $user = new User();
        $user->forceFill(array_merge([
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'user' . uniqid() . '@campus.univ.fr',
            'password' => Hash::make('Password123!'),
            'role' => 'etudiant',
            'statut' => 'actif',
            'filiere' => 'Informatique',
        ], $attributes));
        $user->save();

        return $user;
    }

    public function test_endpoints_proteges_refusent_les_requetes_non_authentifiees(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
        $this->getJson('/api/offres/mes-offres')->assertStatus(401);
        $this->postJson('/api/offres', [])->assertStatus(401);
        $this->postJson('/api/offres/1/creneaux', [])->assertStatus(401);
        $this->postJson('/api/reservations', [])->assertStatus(401);
        $this->getJson('/api/reservations')->assertStatus(401);
        $this->getJson('/api/planning')->assertStatus(401);
        $this->postJson('/api/evaluations', [])->assertStatus(401);
        $this->getJson('/api/admin/stats')->assertStatus(401);
    }

    public function test_etudiant_recoit_403_sur_les_endpoints_administrateurs(): void
    {
        $etudiant = $this->createUser(['role' => 'etudiant', 'statut' => 'actif']);
        Sanctum::actingAs($etudiant);

        $this->getJson('/api/admin/stats')->assertStatus(403);
        $this->getJson('/api/admin/users')->assertStatus(403);
        $this->postJson('/api/admin/categories', ['nom' => 'Nouvelle'])->assertStatus(403);
        $this->getJson('/api/admin/offres')->assertStatus(403);
        $this->postJson('/api/litiges/1/arbitrer', [
            'decision' => 'transfert_intervenant',
            'motif' => 'Tentative étudiant',
        ])->assertStatus(403);
    }

    public function test_etudiant_desactive_recoit_403_sur_les_actions_metier(): void
    {
        $etudiantDesactive = $this->createUser(['role' => 'etudiant', 'statut' => 'desactive']);
        $categorie = Categorie::create(['nom' => 'Développement Web']);

        $autreUser = $this->createUser(['email' => 'autre@campus.univ.fr']);
        $offre = (new Offre())->forceFill([
            'etudiant_id' => $autreUser->id,
            'categorie_id' => $categorie->id,
            'titre' => 'PHP Moderne et Sécurisé',
            'competence' => 'PHP',
            'description' => 'Un cours approfondi sur PHP 8 et les bonnes pratiques.',
            'niveau' => 'debutant',
            'duree' => 1,
            'mode' => 'presentiel',
            'statut' => 'publiee',
        ]);
        $offre->save();

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'lieu_public' => 'Campus Salle 101',
            'lien_ou_lieu_prive' => 'Bâtiment B salle 101',
        ]);

        Sanctum::actingAs($etudiantDesactive);

        // 1. Création d'offre avec données valides -> rejet 403 (compte désactivé)
        $this->postJson('/api/offres', [
            'titre' => 'Offre test étudiant désactivé',
            'categorie_id' => $categorie->id,
            'competence' => 'PHP',
            'description' => 'Description détaillée d au moins vingt caracteres pour validation.',
            'niveau' => 'debutant',
            'duree' => 1,
            'mode' => 'presentiel',
        ])->assertStatus(403);

        // 2. Demande de réservation -> rejet 403 (compte désactivé)
        $this->postJson('/api/reservations', [
            'creneau_id' => $creneau->id,
        ])->assertStatus(403);
    }

    public function test_administrateur_actif_a_acces_au_back_office(): void
    {
        $admin = $this->createUser(['role' => 'administrateur', 'statut' => 'actif']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')->assertStatus(200);
        $this->getJson('/api/admin/users')->assertStatus(200);
        $this->getJson('/api/admin/categories')->assertStatus(200);
        $this->getJson('/api/admin/offres')->assertStatus(200);
    }

    public function test_administrateur_desactive_est_bloque_en_403(): void
    {
        $adminDesactive = $this->createUser(['role' => 'administrateur', 'statut' => 'desactive']);
        Sanctum::actingAs($adminDesactive);

        $this->getJson('/api/admin/stats')->assertStatus(403);
        $this->getJson('/api/admin/users')->assertStatus(403);
    }
}
