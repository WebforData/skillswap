<?php

namespace Tests\Feature;

use App\Http\Resources\CreneauResource;
use App\Http\Resources\PortefeuilleResource;
use App\Http\Resources\UserResource;
use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\Litige;
use App\Models\Offre;
use App\Models\Portefeuille;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiPrivacyTest extends TestCase
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
            'nom' => 'Nom' . uniqid(),
            'prenom' => 'Prenom',
            'email' => 'user' . uniqid() . '@campus.univ.fr',
            'password' => Hash::make('Password123!'),
            'role' => 'etudiant',
            'statut' => 'actif',
            'filiere' => 'Sciences',
        ], $attributes));
        $user->save();

        return $user;
    }

    private function createReservation(array $attributes = []): Reservation
    {
        $res = new Reservation();
        $res->forceFill(array_merge([
            'statut' => 'en_attente',
            'cout_credits' => 1,
            'confirmation_intervenant' => false,
            'confirmation_beneficiaire' => false,
            'date_demande' => Carbon::now(),
        ], $attributes));
        $res->save();

        return $res;
    }

    public function test_catalogue_public_ne_divulgue_jamais_lieu_ou_lien_prive(): void
    {
        $intervenant = $this->createUser();
        $categorie = Categorie::create(['nom' => 'Musique']);

        $offre = (new Offre())->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Cours de Piano',
            'competence' => 'Piano',
            'description' => 'Cours pour débutants et intermédiaires avec solfège.',
            'niveau' => 'debutant',
            'duree' => 1,
            'mode' => 'distanciel',
            'statut' => 'publiee',
        ]);
        $offre->save();

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Visio Zoom',
            'lien_ou_lieu_prive' => 'https://zoom.us/j/999888777?pwd=SuperSecretMeetingPassword',
        ]);

        // Consultation de l'offre publique sans être authentifié
        $response = $this->getJson("/api/offres/{$offre->id}");
        $response->assertStatus(200);

        // La réponse brute de l'API ne doit jamais contenir le lien privé
        $json = $response->getContent();
        $this->assertStringNotContainsString('SuperSecretMeetingPassword', $json);
        $this->assertStringNotContainsString('https://zoom.us', $json);

        $creneaux = $response->json('offre.creneaux');
        $this->assertNotEmpty($creneaux);
        $this->assertArrayNotHasKey('lien_ou_lieu_prive', $creneaux[0]);
    }

    public function test_rg19_reservation_details_prives_conditionnes_a_l_acceptation(): void
    {
        $intervenant = $this->createUser();
        $beneficiaire = $this->createUser();
        $categorie = Categorie::create(['nom' => 'Langues']);

        $offre = (new Offre())->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Conversation Allemand',
            'competence' => 'Allemand',
            'description' => 'Pratique orale pour préparer une certification.',
            'niveau' => 'intermediaire',
            'duree' => 1,
            'mode' => 'presentiel',
            'statut' => 'publiee',
        ]);
        $offre->save();

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'lieu_public' => 'Campus Centre - Bâtiment C',
            'lien_ou_lieu_prive' => 'Salle C-204 digicode 7845A',
        ]);

        $reservation = $this->createReservation([
            'creneau_id' => $creneau->id,
            'beneficiaire_id' => $beneficiaire->id,
            'statut' => 'en_attente',
            'cout_credits' => 1,
        ]);

        // 1. Bénéficiaire en attente -> lieu privé est STRICTEMENT null (RG19)
        Sanctum::actingAs($beneficiaire);
        $resAttente = $this->getJson("/api/reservations/{$reservation->id}");
        $resAttente->assertStatus(200);
        $this->assertNull($resAttente->json('reservation.creneau.lieu_ou_lien_prive'));

        // 2. Intervenant consulte la réservation -> lieu privé toujours visible pour l'intervenant
        Sanctum::actingAs($intervenant);
        $resInterv = $this->getJson("/api/reservations/{$reservation->id}");
        $resInterv->assertStatus(200);
        $this->assertEquals('Salle C-204 digicode 7845A', $resInterv->json('reservation.creneau.lieu_ou_lien_prive'));

        // 3. Réservation acceptée -> bénéficiaire a désormais accès au digicode (RG19)
        $reservation->forceFill(['statut' => 'acceptee'])->save();

        Sanctum::actingAs($beneficiaire);
        $resAcceptee = $this->getJson("/api/reservations/{$reservation->id}");
        $resAcceptee->assertStatus(200);
        $this->assertEquals('Salle C-204 digicode 7845A', $resAcceptee->json('reservation.creneau.lieu_ou_lien_prive'));

        // 4. Réservation refusée -> lieu privé redevient null
        $reservation->forceFill(['statut' => 'refusee'])->save();
        $resRefusee = $this->getJson("/api/reservations/{$reservation->id}");
        $resRefusee->assertStatus(200);
        $this->assertNull($resRefusee->json('reservation.creneau.lieu_ou_lien_prive'));
    }

    public function test_planning_confidentialite_rg19(): void
    {
        $etudiant = $this->createUser();
        $autreIntervenant = $this->createUser();
        $categorie = Categorie::create(['nom' => 'Informatique']);

        // Offre où l'étudiant est bénéficiaire
        $offreExterne = (new Offre())->forceFill([
            'etudiant_id' => $autreIntervenant->id,
            'categorie_id' => $categorie->id,
            'titre' => 'TypeScript Avancé',
            'competence' => 'TypeScript',
            'description' => 'Cours avancé sur les génériques et le typage conditionnel.',
            'niveau' => 'avance',
            'duree' => 1,
            'mode' => 'visio',
            'statut' => 'publiee',
        ]);
        $offreExterne->save();

        $creneauAttente = Creneau::create([
            'offre_id' => $offreExterne->id,
            'date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Visio',
            'lien_ou_lieu_prive' => 'https://meet.jit.si/secret-attente-777',
        ]);

        $this->createReservation([
            'creneau_id' => $creneauAttente->id,
            'beneficiaire_id' => $etudiant->id,
            'statut' => 'en_attente',
        ]);

        $creneauAccepte = Creneau::create([
            'offre_id' => $offreExterne->id,
            'date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'heure_debut' => '16:00',
            'heure_fin' => '17:00',
            'lieu_public' => 'Visio',
            'lien_ou_lieu_prive' => 'https://meet.jit.si/secret-accepte-888',
        ]);

        $this->createReservation([
            'creneau_id' => $creneauAccepte->id,
            'beneficiaire_id' => $etudiant->id,
            'statut' => 'acceptee',
        ]);

        Sanctum::actingAs($etudiant);
        $response = $this->getJson('/api/planning');
        $response->assertStatus(200);

        $planning = $response->json('planning');
        $this->assertNotEmpty($planning);

        foreach ($planning as $item) {
            if ($item['creneau']['id'] === $creneauAttente->id) {
                $this->assertNull($item['creneau']['lieu_ou_lien_prive'], 'Le lien privé en attente doit être masqué (RG19)');
            }
            if ($item['creneau']['id'] === $creneauAccepte->id) {
                $this->assertEquals('https://meet.jit.si/secret-accepte-888', $item['creneau']['lieu_ou_lien_prive']);
            }
        }
    }

    public function test_confidentialite_financiere_portefeuille_ne_fuit_jamais_aux_tiers(): void
    {
        $etudiantA = $this->createUser();
        $etudiantB = $this->createUser();

        $portefeuille = (new Portefeuille())->forceFill([
            'etudiant_id' => $etudiantA->id,
            'solde_disponible' => 50,
            'credits_bloques' => 10,
        ]);
        $portefeuille->save();

        // 1. Tiers étudiant B inspecte le portefeuille de A via UserResource
        $etudiantA->load('portefeuille');
        Sanctum::actingAs($etudiantB);
        $requestTiers = Request::create('/api/users/' . $etudiantA->id, 'GET');
        $this->app->instance('request', $requestTiers);

        $resTiers = (new UserResource($etudiantA))->response($requestTiers)->getData(true)['data'];

        // L'email et le portefeuille ne doivent JAMAIS apparaître pour un tiers (RG16, RG19)
        $this->assertArrayNotHasKey('email', $resTiers);
        $this->assertArrayNotHasKey('portefeuille', $resTiers);

        // 2. Test direct de PortefeuilleResource par un tiers : solde strictement masqué
        $resPortefeuilleTiers = (new PortefeuilleResource($portefeuille))->response($requestTiers)->getData(true)['data'];
        $this->assertArrayNotHasKey('solde_disponible', $resPortefeuilleTiers);
        $this->assertArrayNotHasKey('credits_bloques', $resPortefeuilleTiers);
        $this->assertArrayNotHasKey('solde_total', $resPortefeuilleTiers);

        // 3. Propriétaire consulte ses données via UserResource et PortefeuilleResource
        Sanctum::actingAs($etudiantA);
        $requestOwner = Request::create('/api/auth/me', 'GET');
        $this->app->instance('request', $requestOwner);

        $resOwner = (new UserResource($etudiantA))->response($requestOwner)->getData(true)['data'];
        $this->assertEquals($etudiantA->email, $resOwner['email']);
        $this->assertEquals(50, $resOwner['portefeuille']['solde_disponible']);
        $this->assertEquals(10, $resOwner['portefeuille']['credits_bloques']);
        $this->assertEquals(60, $resOwner['portefeuille']['solde_total']);
    }
}
