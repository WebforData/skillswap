<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Portefeuille;
use App\Models\Categorie;
use App\Models\Offre;
use App\Models\Creneau;
use App\Models\Reservation;
use App\Models\MouvementCredit;
use App\Models\Evaluation;
use App\Models\Litige;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class SecurityAndDatabaseRelationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configuration d'une base SQLite en mémoire pour tester les requêtes réelles
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createTestDatabaseSchema();
    }

    /**
     * Schéma SQLite simplifié en mémoire pour valider les modèles et relations réelles.
     */
    protected function createTestDatabaseSchema(): void
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
            $table->timestamp('date_demande')->nullable();
            $table->string('statut')->default('en_attente');
            $table->tinyInteger('cout_credits')->default(1);
            $table->text('motif_refus')->nullable();
            $table->boolean('confirmation_intervenant')->default(false);
            $table->timestamp('date_confirmation_intervenant')->nullable();
            $table->boolean('confirmation_beneficiaire')->default(false);
            $table->timestamp('date_confirmation_beneficiaire')->nullable();
            $table->timestamps();
        });

        Schema::create('mouvements_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portefeuille_id')->constrained('portefeuilles');
            $table->foreignId('reservation_id')->nullable()->constrained('reservations');
            $table->timestamp('date_mouvement')->nullable();
            $table->integer('quantite');
            $table->string('type');
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

    /**
     * TEST 1 : Vérifie la persistance et les relations réelles en base de données.
     */
    public function test_real_database_relations(): void
    {
        // 1. Création de deux étudiants et d'un administrateur
        $intervenant = new User([
            'nom' => 'Alami',
            'prenom' => 'Sara',
            'email' => 'sara@estbm.ac.ma',
            'password' => 'secret123',
            'filiere' => 'GI',
        ]);
        $intervenant->save();

        $beneficiaire = new User([
            'nom' => 'Benali',
            'prenom' => 'Amine',
            'email' => 'amine@estbm.ac.ma',
            'password' => 'secret123',
            'filiere' => 'TM',
        ]);
        $beneficiaire->save();

        $admin = new User([
            'nom' => 'Admin',
            'prenom' => 'Root',
            'email' => 'admin@estbm.ac.ma',
            'password' => 'adminpwd',
        ]);
        $admin->role = 'administrateur';
        $admin->save();

        // 2. Portefeuilles
        $portefeuilleSara = new Portefeuille(['etudiant_id' => $intervenant->id]);
        $portefeuilleSara->solde_disponible = 2;
        $portefeuilleSara->save();

        $this->assertEquals($intervenant->id, $portefeuilleSara->etudiant->id);
        $this->assertEquals($portefeuilleSara->id, $intervenant->portefeuille->id);

        // 3. Catégorie et Offre
        $categorie = Categorie::create(['nom' => 'Informatique']);
        
        $offre = new Offre([
            'categorie_id' => $categorie->id,
            'titre' => 'Initiation Python',
            'competence' => 'Python',
            'description' => 'Cours pratique 1h',
            'niveau' => 'debutant',
            'duree' => 1,
            'mode' => 'presentiel',
        ]);
        $offre->etudiant_id = $intervenant->id;
        $offre->statut = 'publiee';
        $offre->save();

        // Vérification de la propriété de l'offre
        $this->assertEquals($intervenant->id, $offre->intervenant->id);
        $this->assertEquals('Sara', $offre->intervenant->prenom);
        $this->assertEquals('Informatique', $offre->categorie->nom);

        // 4. Créneau
        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => '2026-11-20',
            'heure_debut' => '10:00:00',
            'heure_fin' => '11:00:00',
            'lieu_public' => 'Salle B12',
            'lien_ou_lieu_prive' => 'Salle B12 - Code 7890',
        ]);
        $this->assertEquals($offre->id, $creneau->offre->id);

        // 5. Réservation
        $reservation = new Reservation([
            'creneau_id' => $creneau->id,
        ]);
        $reservation->beneficiaire_id = $beneficiaire->id;
        $reservation->cout_credits = 1;
        $reservation->statut = 'acceptee';
        $reservation->save();

        // Distinction stricte entre intervenant et bénéficiaire
        $this->assertEquals($beneficiaire->id, $reservation->beneficiaire->id);
        $this->assertEquals('Amine', $reservation->beneficiaire->prenom);
        $this->assertEquals($intervenant->id, $reservation->intervenant->id);
        $this->assertEquals('Sara', $reservation->intervenant->prenom);
        $this->assertNotEquals($reservation->beneficiaire->id, $reservation->intervenant->id);

        $this->assertTrue($reservation->isBeneficiaire($beneficiaire));
        $this->assertTrue($reservation->isIntervenant($intervenant));
        $this->assertFalse($reservation->isBeneficiaire($intervenant));
        $this->assertFalse($reservation->isIntervenant($beneficiaire));

        // 6. Mouvement de crédits
        $mvt = new MouvementCredit();
        $mvt->forceFill([
            'portefeuille_id' => $portefeuilleSara->id,
            'reservation_id' => $reservation->id,
            'quantite' => 1,
            'type' => 'transfert_gain',
        ])->save();

        $this->assertEquals($portefeuilleSara->id, $mvt->portefeuille->id);
        $this->assertEquals($reservation->id, $mvt->reservation->id);
        $this->assertEquals(1, $reservation->mouvementsCredits()->count());

        // 7. Évaluations
        $evalSara = new Evaluation([
            'reservation_id' => $reservation->id,
            'note' => 5,
            'commentaire' => 'Excellente séance !',
        ]);
        $evalSara->auteur_id = $beneficiaire->id;
        $evalSara->cible_id = $intervenant->id;
        $evalSara->save();

        $this->assertEquals($reservation->id, $evalSara->reservation->id);
        $this->assertEquals($beneficiaire->id, $evalSara->auteur->id);
        $this->assertEquals($intervenant->id, $evalSara->cible->id);
        $this->assertEquals(1, $intervenant->evaluationsRecues()->count());

        // 8. Litige
        $litige = new Litige([
            'reservation_id' => $reservation->id,
            'description' => 'Désaccord sur les horaires',
        ]);
        $litige->declarant_id = $beneficiaire->id;
        $litige->admin_id = $admin->id;
        $litige->statut = 'resolu';
        $litige->decision = 'transfert_intervenant';
        $litige->motif_decision = 'Séance vérifiée';
        $litige->save();

        $this->assertEquals($reservation->id, $litige->reservation->id);
        $this->assertEquals($beneficiaire->id, $litige->declarant->id);
        $this->assertEquals($admin->id, $litige->administrateur->id);
    }

    /**
     * TEST 2 : Sécurité du Mass-Assignment (tentatives d'injection rejetées).
     */
    public function test_mass_assignment_protection(): void
    {
        // A. Tentative d'élévation de privilèges sur User
        $user = new User([
            'nom' => 'Hacker',
            'prenom' => 'Bob',
            'email' => 'hacker@estbm.ac.ma',
            'password' => 'secret',
            'role' => 'administrateur', // INJECTION
            'statut' => 'desactive',    // INJECTION
        ]);
        $this->assertEquals('etudiant', $user->role, 'Le rôle doit rester la valeur par défaut du modèle.');
        $this->assertEquals('actif', $user->statut, 'Le statut ne doit pas être altéré par mass-assignment.');

        // B. Tentative d'usurpation de propriété sur Offre
        $offre = new Offre([
            'etudiant_id' => 9999, // INJECTION
            'statut' => 'publiee',  // INJECTION
            'titre' => 'Titre test',
        ]);
        $this->assertNull($offre->etudiant_id, 'etudiant_id ne doit pas être assignable par mass-assignment.');
        $this->assertEquals('brouillon', $offre->statut, 'Le statut doit rester brouillon par défaut.');

        // C. Tentative de falsification sur Reservation
        $res = new Reservation([
            'creneau_id' => 1,
            'beneficiaire_id' => 8888,          // INJECTION
            'statut' => 'realisee',             // INJECTION
            'cout_credits' => 0,                // INJECTION
            'confirmation_intervenant' => true, // INJECTION
            'confirmation_beneficiaire' => true,// INJECTION
        ]);
        $this->assertNull($res->beneficiaire_id, 'beneficiaire_id ne doit pas être assignable par mass-assignment.');
        $this->assertEquals('en_attente', $res->statut, 'Le statut ne doit pas être falsifiable.');
        $this->assertFalse((bool)$res->confirmation_intervenant);
        $this->assertFalse((bool)$res->confirmation_beneficiaire);

        // D. Protection de l'auteur et de la cible sur Evaluation
        $eval = new Evaluation([
            'reservation_id' => 1,
            'auteur_id' => 7777, // INJECTION
            'cible_id' => 6666,  // INJECTION
            'note' => 5,
        ]);
        $this->assertNull($eval->auteur_id, 'auteur_id protégé.');
        $this->assertNull($eval->cible_id, 'cible_id protégé.');

        // E. Protection de l'arbitrage sur Litige
        $litige = new Litige([
            'reservation_id' => 1,
            'declarant_id' => 5555,                   // INJECTION
            'admin_id' => 4444,                       // INJECTION
            'statut' => 'resolu',                     // INJECTION
            'decision' => 'restitution_beneficiaire', // INJECTION
        ]);
        $this->assertNull($litige->declarant_id);
        $this->assertNull($litige->admin_id);
        $this->assertEquals('ouvert', $litige->statut);
        $this->assertNull($litige->decision);

        // F. Protection absolue de MouvementCredit : mass-assignment totalement interdit
        try {
            new MouvementCredit([
                'portefeuille_id' => 1,
                'quantite' => 1000,
                'type' => 'bienvenue',
            ]);
            $this->fail("MouvementCredit aurait dû lever une MassAssignmentException.");
        } catch (\Illuminate\Database\Eloquent\MassAssignmentException $e) {
            $this->assertTrue(true, "Mass-assignment strictement interdit sur MouvementCredit.");
        }

        // G. Protection absolue des soldes sur Portefeuille
        $portefeuille = new Portefeuille([
            'etudiant_id' => 1,
            'solde_disponible' => 500, // INJECTION
            'credits_bloques' => 200,  // INJECTION
        ]);
        $this->assertEquals(1, $portefeuille->etudiant_id);
        $this->assertEquals(0, $portefeuille->solde_disponible);
        $this->assertEquals(0, $portefeuille->credits_bloques);
    }

    /**
     * TEST 3 : Confidentialité des données privées (RG19).
     */
    public function test_privacy_and_confidentiality_protection(): void
    {
        $offre = new Offre(['titre' => 'Offre test']);
        $offre->etudiant_id = 10; // Intervenant = ID 10

        $creneau = new Creneau([
            'date' => '2026-11-20',
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Campus EST',
            'lien_ou_lieu_prive' => 'https://meet.google.com/prive-secret-xyz',
        ]);
        $creneau->setRelation('offre', $offre);

        // 1. Masquage automatique lors de la sérialisation JSON
        $json = $creneau->toArray();
        $this->assertArrayNotHasKey('lien_ou_lieu_prive', $json, 'Le lien privé doit être absent du toArray/JSON.');

        // 2. Vérification des droits d'accès programmatiques
        $intervenant = new User();
        $intervenant->id = 10;

        $tiers = new User();
        $tiers->id = 99;

        $admin = new User();
        $admin->id = 1;
        $admin->role = 'administrateur';

        $this->assertTrue($creneau->peutAccederAuxDetailsPrives($intervenant));
        $this->assertTrue($creneau->peutAccederAuxDetailsPrives($admin));
        $this->assertFalse($creneau->peutAccederAuxDetailsPrives($tiers));

        $this->assertEquals('https://meet.google.com/prive-secret-xyz', $creneau->getLieuOuLienSecurise($intervenant));
        $this->assertNull($creneau->getLieuOuLienSecurise($tiers));
    }
}
