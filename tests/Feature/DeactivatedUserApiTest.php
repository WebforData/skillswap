<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Portefeuille;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeactivatedUserApiTest extends TestCase
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

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations');
            $table->foreignId('auteur_id')->constrained('users');
            $table->foreignId('destinataire_id')->constrained('users');
            $table->tinyInteger('note');
            $table->text('commentaire')->nullable();
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
    }

    private function createDeactivatedUser(): User
    {
        $user = new User();
        $user->forceFill([
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'email' => 'jean.desactive@univ-paris.fr',
            'password' => Hash::make('Secret123!'),
            'role' => 'etudiant',
            'statut' => 'desactive',
            'filiere' => 'Informatique',
        ]);
        $user->save();

        $portefeuille = new Portefeuille();
        $portefeuille->forceFill([
            'etudiant_id' => $user->id,
            'solde_disponible' => 2,
            'credits_bloques' => 0,
        ]);
        $portefeuille->save();

        return $user;
    }

    private function createActiveUser(): User
    {
        $user = new User();
        $user->forceFill([
            'nom' => 'Martin',
            'prenom' => 'Claire',
            'email' => 'claire.active@univ-paris.fr',
            'password' => Hash::make('Secret123!'),
            'role' => 'etudiant',
            'statut' => 'actif',
            'filiere' => 'Maths',
        ]);
        $user->save();

        $portefeuille = new Portefeuille();
        $portefeuille->forceFill([
            'etudiant_id' => $user->id,
            'solde_disponible' => 2,
            'credits_bloques' => 0,
        ]);
        $portefeuille->save();

        return $user;
    }

    public function test_compte_desactive_bloque_sur_auth_me_avec_http_403(): void
    {
        $user = $this->createDeactivatedUser();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'CompteDesactiveException',
        ]);
        $this->assertStringContainsString('désactivé', $response->json('message'));
    }

    public function test_compte_desactive_bloque_sur_planning_avec_http_403(): void
    {
        $user = $this->createDeactivatedUser();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/planning');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'CompteDesactiveException',
        ]);
    }

    public function test_compte_desactive_bloque_sur_reservations_avec_http_403(): void
    {
        $user = $this->createDeactivatedUser();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reservations');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'CompteDesactiveException',
        ]);
    }

    public function test_compte_desactive_bloque_sur_mes_offres_avec_http_403(): void
    {
        $user = $this->createDeactivatedUser();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/offres/mes-offres');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'CompteDesactiveException',
        ]);
    }

    public function test_compte_actif_peut_acceder_aux_routes_protegees(): void
    {
        $user = $this->createActiveUser();
        Sanctum::actingAs($user);

        $responseMe = $this->getJson('/api/auth/me');
        $responseMe->assertStatus(200);
        $responseMe->assertJsonPath('user.email', 'claire.active@univ-paris.fr');

        $responsePlanning = $this->getJson('/api/planning');
        $responsePlanning->assertStatus(200);
    }

    public function test_routes_publiques_restent_accessibles_sans_authentification(): void
    {
        $responseOffres = $this->getJson('/api/offres');
        $responseOffres->assertStatus(200);
    }
}
