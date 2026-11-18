<?php

namespace Tests\Feature;

use App\Http\Resources\CreneauResource;
use App\Http\Resources\EvaluationResource;
use App\Http\Resources\LitigeResource;
use App\Http\Resources\OffreResource;
use App\Http\Resources\PortefeuilleResource;
use App\Http\Resources\ReservationResource;
use App\Http\Resources\UserResource;
use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\Evaluation;
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
use Tests\TestCase;

class ApiResourcesTest extends TestCase
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
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'email' => 'jean.dupont@campus.univ.fr',
            'password' => Hash::make('Secret123!'),
            'role' => 'etudiant',
            'statut' => 'actif',
            'filiere' => 'Informatique',
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

    public function test_user_resource_ne_divulgue_jamais_mot_de_passe_ou_token(): void
    {
        $user = $this->createUser();
        $request = Request::create('/api/me', 'GET');
        $request->setUserResolver(fn() => $user);

        $resource = (new UserResource($user))->toArray($request);

        $this->assertArrayNotHasKey('password', $resource);
        $this->assertArrayNotHasKey('remember_token', $resource);
        $this->assertEquals('Jean Dupont', $resource['nom_complet']);
        $this->assertEquals($user->email, $resource['email']);
    }

    public function test_user_resource_masque_email_aux_tiers(): void
    {
        $etudiantA = $this->createUser(['email' => 'a@campus.univ.fr']);
        $etudiantB = $this->createUser(['email' => 'b@campus.univ.fr']);

        $request = Request::create('/api/users/' . $etudiantA->id, 'GET');
        $request->setUserResolver(fn() => $etudiantB);

        $resource = (new UserResource($etudiantA))->resolve($request);

        $this->assertArrayNotHasKey('email', $resource);
    }

    public function test_user_resource_expose_email_a_l_administrateur(): void
    {
        $etudiant = $this->createUser(['email' => 'etudiant@campus.univ.fr']);
        $admin = $this->createUser(['role' => 'administrateur', 'email' => 'admin@campus.univ.fr']);

        $request = Request::create('/api/admin/utilisateurs/' . $etudiant->id, 'GET');
        $request->setUserResolver(fn() => $admin);

        $resource = (new UserResource($etudiant))->resolve($request);

        $this->assertEquals('etudiant@campus.univ.fr', $resource['email']);
    }

    public function test_portefeuille_resource_masque_les_soldes_financiers_aux_tiers(): void
    {
        $proprietaire = $this->createUser(['email' => 'proprio@campus.univ.fr']);
        $tiers = $this->createUser(['email' => 'tiers@campus.univ.fr']);

        $portefeuille = (new Portefeuille())->forceFill([
            'etudiant_id' => $proprietaire->id,
            'solde_disponible' => 10,
            'credits_bloques' => 2,
        ]);
        $portefeuille->save();

        // 1. Consultation par un tiers : solde masqué
        $requestTiers = Request::create('/api/portefeuille', 'GET');
        $requestTiers->setUserResolver(fn() => $tiers);

        $resourceTiers = (new PortefeuilleResource($portefeuille))->resolve($requestTiers);
        $this->assertArrayNotHasKey('solde_disponible', $resourceTiers);
        $this->assertArrayNotHasKey('credits_bloques', $resourceTiers);
        $this->assertArrayNotHasKey('solde_total', $resourceTiers);

        // 2. Consultation par le propriétaire : soldes visibles
        $requestOwner = Request::create('/api/portefeuille', 'GET');
        $requestOwner->setUserResolver(fn() => $proprietaire);

        $resourceOwner = (new PortefeuilleResource($portefeuille))->resolve($requestOwner);
        $this->assertEquals(10, $resourceOwner['solde_disponible']);
        $this->assertEquals(2, $resourceOwner['credits_bloques']);
        $this->assertEquals(12, $resourceOwner['solde_total']);
    }

    public function test_creneau_resource_rg19_masque_lieu_prive_aux_non_participants(): void
    {
        $intervenant = $this->createUser(['email' => 'prof@campus.univ.fr']);
        $beneficiaire = $this->createUser(['email' => 'eleve@campus.univ.fr']);
        $tiers = $this->createUser(['email' => 'curieux@campus.univ.fr']);
        $admin = $this->createUser(['role' => 'administrateur', 'email' => 'admin@campus.univ.fr']);

        $categorie = Categorie::create(['nom' => 'Musique']);
        $offre = (new Offre())->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Guitare classique',
            'competence' => 'Musique',
            'description' => 'Cours individuel',
            'niveau' => 'debutant',
            'duree' => 1,
            'mode' => 'visio',
            'statut' => 'publiee',
        ]);
        $offre->save();

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Plateforme en ligne',
            'lien_ou_lieu_prive' => 'https://meet.google.com/secret-room-123',
        ]);

        // Tiers curieux : lien privé doit être null
        $reqTiers = Request::create('/api/creneaux', 'GET');
        $reqTiers->setUserResolver(fn() => $tiers);
        $resTiers = (new CreneauResource($creneau))->resolve($reqTiers);
        $this->assertNull($resTiers['lieu_ou_lien_prive']);
        $this->assertEquals('Plateforme en ligne', $resTiers['lieu_public']);

        // Visiteur anonyme : lien privé doit être null
        $reqAnonyme = Request::create('/api/creneaux', 'GET');
        $resAnonyme = (new CreneauResource($creneau))->resolve($reqAnonyme);
        $this->assertNull($resAnonyme['lieu_ou_lien_prive']);

        // Intervenant : lien privé accessible
        $reqIntervenant = Request::create('/api/creneaux', 'GET');
        $reqIntervenant->setUserResolver(fn() => $intervenant);
        $resIntervenant = (new CreneauResource($creneau))->resolve($reqIntervenant);
        $this->assertEquals('https://meet.google.com/secret-room-123', $resIntervenant['lieu_ou_lien_prive']);

        // Admin : lien privé accessible
        $reqAdmin = Request::create('/api/creneaux', 'GET');
        $reqAdmin->setUserResolver(fn() => $admin);
        $resAdmin = (new CreneauResource($creneau))->resolve($reqAdmin);
        $this->assertEquals('https://meet.google.com/secret-room-123', $resAdmin['lieu_ou_lien_prive']);

        // Bénéficiaire en attente : lien privé masqué tant que non accepté
        $reservation = $this->createReservation([
            'creneau_id' => $creneau->id,
            'beneficiaire_id' => $beneficiaire->id,
            'date_demande' => Carbon::now(),
            'statut' => 'en_attente',
            'cout_credits' => 1,
        ]);
        $reqBenef = Request::create('/api/creneaux', 'GET');
        $reqBenef->setUserResolver(fn() => $beneficiaire);
        $resBenefAttente = (new CreneauResource($creneau))->resolve($reqBenef);
        $this->assertNull($resBenefAttente['lieu_ou_lien_prive']);

        // Bénéficiaire accepté : lien privé devient accessible
        $reservation->forceFill(['statut' => 'acceptee'])->save();
        $resBenefAccepte = (new CreneauResource($creneau))->resolve($reqBenef);
        $this->assertEquals('https://meet.google.com/secret-room-123', $resBenefAccepte['lieu_ou_lien_prive']);
    }

    public function test_litige_resource_confidentialite_tiers(): void
    {
        $intervenant = $this->createUser(['email' => 'interv@campus.univ.fr']);
        $beneficiaire = $this->createUser(['email' => 'benef@campus.univ.fr']);
        $tiers = $this->createUser(['email' => 'tiers2@campus.univ.fr']);
        $admin = $this->createUser(['role' => 'administrateur', 'email' => 'admin2@campus.univ.fr']);

        $categorie = Categorie::create(['nom' => 'Code']);
        $offre = (new Offre())->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $categorie->id,
            'titre' => 'PHP Expert',
            'competence' => 'PHP',
            'description' => 'Cours PHP',
            'niveau' => 'avance',
            'duree' => 1,
            'mode' => 'visio',
            'statut' => 'publiee',
        ]);
        $offre->save();

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDay()->format('Y-m-d'),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'lieu_public' => 'Visio',
            'lien_ou_lieu_prive' => 'https://meet.example.com',
        ]);

        $reservation = $this->createReservation([
            'creneau_id' => $creneau->id,
            'beneficiaire_id' => $beneficiaire->id,
            'date_demande' => Carbon::now(),
            'statut' => 'en_litige',
            'cout_credits' => 1,
        ]);

        $litige = (new Litige())->forceFill([
            'reservation_id' => $reservation->id,
            'declarant_id' => $beneficiaire->id,
            'description' => 'Intervenant absent sans justification.',
            'statut' => 'ouvert',
        ]);
        $litige->save();

        // Tiers : voit uniquement id et statut, description masquée
        $reqTiers = Request::create('/api/litiges/' . $litige->id, 'GET');
        $reqTiers->setUserResolver(fn() => $tiers);
        $resTiers = (new LitigeResource($litige))->resolve($reqTiers);

        $this->assertEquals($litige->id, $resTiers['id']);
        $this->assertEquals('ouvert', $resTiers['statut']);
        $this->assertArrayNotHasKey('description', $resTiers);

        // Déclarant : voit tous les détails
        $reqDeclarant = Request::create('/api/litiges/' . $litige->id, 'GET');
        $reqDeclarant->setUserResolver(fn() => $beneficiaire);
        $resDeclarant = (new LitigeResource($litige))->resolve($reqDeclarant);

        $this->assertEquals('Intervenant absent sans justification.', $resDeclarant['description']);
        $this->assertEquals('ouvert', $resDeclarant['statut']);

        // Admin : voit tous les détails
        $reqAdmin = Request::create('/api/litiges/' . $litige->id, 'GET');
        $reqAdmin->setUserResolver(fn() => $admin);
        $resAdmin = (new LitigeResource($litige))->resolve($reqAdmin);

        $this->assertEquals('Intervenant absent sans justification.', $resAdmin['description']);
    }

    public function test_evaluation_resource_transformation(): void
    {
        $auteur = $this->createUser(['email' => 'eval_auteur@campus.univ.fr']);
        $cible = $this->createUser(['email' => 'eval_cible@campus.univ.fr']);

        $categorie = Categorie::create(['nom' => 'Langues']);
        $offre = (new Offre())->forceFill([
            'etudiant_id' => $cible->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Anglais C1',
            'competence' => 'Anglais',
            'description' => 'Perfectionnement',
            'niveau' => 'avance',
            'duree' => 1,
            'mode' => 'presentiel',
            'statut' => 'publiee',
        ]);
        $offre->save();

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->subDays(2)->format('Y-m-d'),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'lieu_public' => 'Salle B12',
            'lien_ou_lieu_prive' => 'Salle B12 code 4589',
        ]);

        $reservation = $this->createReservation([
            'creneau_id' => $creneau->id,
            'beneficiaire_id' => $auteur->id,
            'date_demande' => Carbon::now()->subDays(3),
            'statut' => 'realisee',
            'cout_credits' => 1,
        ]);

        $evaluation = (new Evaluation())->forceFill([
            'reservation_id' => $reservation->id,
            'auteur_id' => $auteur->id,
            'cible_id' => $cible->id,
            'note' => 5,
            'commentaire' => 'Excellente pédagogie, très clair !',
        ]);
        $evaluation->save();

        $request = Request::create('/api/evaluations', 'GET');
        $res = (new EvaluationResource($evaluation))->resolve($request);

        $this->assertEquals(5, $res['note']);
        $this->assertEquals('Excellente pédagogie, très clair !', $res['commentaire']);
        $this->assertEquals($auteur->id, $res['auteur_id']);
        $this->assertEquals($cible->id, $res['cible_id']);
    }

    public function test_offre_resource_avec_relations_chargees(): void
    {
        $intervenant = $this->createUser(['email' => 'offre_interv@campus.univ.fr']);
        $categorie = Categorie::create(['nom' => 'Design 3D']);

        $offre = (new Offre())->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Modélisation Blender',
            'competence' => 'Blender',
            'description' => 'Initiation à la 3D',
            'niveau' => 'debutant',
            'duree' => 2,
            'mode' => 'distanciel',
            'statut' => 'publiee',
        ]);
        $offre->save();

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'heure_debut' => '14:00',
            'heure_fin' => '16:00',
            'lieu_public' => 'Discord Campus',
            'lien_ou_lieu_prive' => 'https://discord.gg/secret-room',
        ]);

        $offre->load(['categorie', 'intervenant', 'creneaux']);

        \Laravel\Sanctum\Sanctum::actingAs($intervenant);
        $request = Request::create('/api/offres/' . $offre->id, 'GET');
        $this->app->instance('request', $request);

        $res = (new OffreResource($offre))->response($request)->getData(true)['data'];

        $this->assertEquals($offre->id, $res['id']);
        $this->assertEquals('Modélisation Blender', $res['titre']);
        $this->assertEquals(2, $res['duree']);
        $this->assertEquals('Design 3D', $res['categorie']['nom']);
        $this->assertEquals('Jean Dupont', $res['intervenant']['nom_complet']);
        $this->assertCount(1, $res['creneaux']);
        $this->assertEquals('https://discord.gg/secret-room', $res['creneaux'][0]['lieu_ou_lien_prive']);
    }

    public function test_reservation_resource_avec_relations_chargees(): void
    {
        $intervenant = $this->createUser(['email' => 'res_interv@campus.univ.fr']);
        $beneficiaire = $this->createUser(['email' => 'res_benef@campus.univ.fr']);
        $categorie = Categorie::create(['nom' => 'Physique']);

        $offre = (new Offre())->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Mécanique quantique',
            'competence' => 'Physique',
            'description' => 'Cours avancé',
            'niveau' => 'avance',
            'duree' => 1,
            'mode' => 'presentiel',
            'statut' => 'publiee',
        ]);
        $offre->save();

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'heure_debut' => '09:00',
            'heure_fin' => '10:00',
            'lieu_public' => 'Amphi A',
            'lien_ou_lieu_prive' => 'Amphi A - Rang 3',
        ]);

        $reservation = $this->createReservation([
            'creneau_id' => $creneau->id,
            'beneficiaire_id' => $beneficiaire->id,
            'statut' => 'acceptee',
            'cout_credits' => 1,
            'confirmation_intervenant' => true,
        ]);

        $reservation->load(['creneau.offre.intervenant', 'beneficiaire']);

        \Laravel\Sanctum\Sanctum::actingAs($beneficiaire);
        $request = Request::create('/api/reservations/' . $reservation->id, 'GET');
        $this->app->instance('request', $request);

        $res = (new ReservationResource($reservation))->response($request)->getData(true)['data'];

        $this->assertEquals($reservation->id, $res['id']);
        $this->assertEquals('acceptee', $res['statut']);
        $this->assertEquals(1, $res['cout_credits']);
        $this->assertTrue($res['confirmation_intervenant']);
        $this->assertFalse($res['confirmation_beneficiaire']);
        $this->assertNotNull($res['creneau']);
        $this->assertEquals('Amphi A - Rang 3', $res['creneau']['lieu_ou_lien_prive']);
        $this->assertEquals('Jean Dupont', $res['beneficiaire']['nom_complet']);
    }
}
