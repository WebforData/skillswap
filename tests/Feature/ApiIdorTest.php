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

class ApiIdorTest extends TestCase
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

    public function test_idor_protection_sur_les_offres(): void
    {
        $proprietaire = $this->createUser();
        $usurpateur = $this->createUser();
        $categorie = Categorie::create(['nom' => 'Physique']);

        $offre = (new Offre())->forceFill([
            'etudiant_id' => $proprietaire->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Physique Quantique',
            'competence' => 'Physique',
            'description' => 'Description détaillée d au moins vingt caracteres.',
            'niveau' => 'avance',
            'duree' => 1,
            'mode' => 'presentiel',
            'statut' => 'brouillon',
        ]);
        $offre->save();

        Sanctum::actingAs($usurpateur);

        // 1. Usurpateur tente de modifier l'offre d'autrui
        $this->putJson("/api/offres/{$offre->id}", [
            'titre' => 'Offre piratée par un tiers',
        ])->assertStatus(403);

        // 2. Usurpateur tente de publier l'offre d'autrui
        $this->postJson("/api/offres/{$offre->id}/publier")->assertStatus(403);

        // 3. Usurpateur tente de repasser en brouillon l'offre d'autrui
        $this->postJson("/api/offres/{$offre->id}/brouillon")->assertStatus(403);

        // 4. Usurpateur tente d'archiver l'offre d'autrui
        $this->postJson("/api/offres/{$offre->id}/archiver")->assertStatus(403);
    }

    public function test_idor_protection_sur_les_creneaux(): void
    {
        $intervenant = $this->createUser();
        $usurpateur = $this->createUser();
        $categorie = Categorie::create(['nom' => 'Maths']);

        $offre = (new Offre())->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Algèbre Linéaire',
            'competence' => 'Maths',
            'description' => 'Cours complet sur les espaces vectoriels et matrices.',
            'niveau' => 'intermediaire',
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
            'lieu_public' => 'Salle A01',
            'lien_ou_lieu_prive' => 'Secret Salle A01',
        ]);

        Sanctum::actingAs($usurpateur);

        // 1. Usurpateur tente d'ajouter un créneau sur l'offre d'autrui
        $this->postJson("/api/offres/{$offre->id}/creneaux", [
            'date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'heure_debut' => '14:00',
            'heure_fin' => '15:00',
            'lieu_public' => 'Bibliothèque',
            'lien_ou_lieu_prive' => 'Table 4',
        ])->assertStatus(403);

        // 2. Usurpateur tente de modifier le créneau d'autrui
        $this->putJson("/api/creneaux/{$creneau->id}", [
            'heure_debut' => '11:00',
            'heure_fin' => '12:00',
        ])->assertStatus(403);

        // 3. Usurpateur tente de supprimer le créneau d'autrui
        $this->deleteJson("/api/creneaux/{$creneau->id}")->assertStatus(403);
    }

    public function test_idor_protection_sur_le_cycle_de_vie_des_reservations(): void
    {
        $intervenant = $this->createUser();
        $beneficiaire = $this->createUser();
        $tiers = $this->createUser();
        $categorie = Categorie::create(['nom' => 'Informatique']);

        $offre = (new Offre())->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Architecture REST et Sécurité',
            'competence' => 'Architecture',
            'description' => 'Sécurisation approfondie des API REST et contrôles IDOR.',
            'niveau' => 'avance',
            'duree' => 1,
            'mode' => 'presentiel',
            'statut' => 'publiee',
        ]);
        $offre->save();

        $creneau = Creneau::create([
            'offre_id' => $offre->id,
            'date' => Carbon::now()->addDays(4)->format('Y-m-d'),
            'heure_debut' => '16:00',
            'heure_fin' => '17:00',
            'lieu_public' => 'Lab Informatique',
            'lien_ou_lieu_prive' => 'Poste 12 - mot de passe test',
        ]);

        $reservation = $this->createReservation([
            'creneau_id' => $creneau->id,
            'beneficiaire_id' => $beneficiaire->id,
            'statut' => 'en_attente',
            'cout_credits' => 1,
        ]);

        Sanctum::actingAs($tiers);

        // 1. Tiers tente de consulter la réservation (RG19)
        $this->getJson("/api/reservations/{$reservation->id}")->assertStatus(403);

        // 2. Tiers tente d'accepter la réservation
        $this->postJson("/api/reservations/{$reservation->id}/accepter")->assertStatus(403);

        // 3. Tiers tente de refuser la réservation
        $this->postJson("/api/reservations/{$reservation->id}/refuser", [
            'motif' => 'Tentative de refus illégitime',
        ])->assertStatus(403);

        // 4. Tiers tente de retirer la demande
        $this->postJson("/api/reservations/{$reservation->id}/retirer")->assertStatus(403);

        // 5. Tiers tente d'annuler la réservation
        $this->postJson("/api/reservations/{$reservation->id}/annuler", [
            'motif' => 'Tentative d annulation illégitime',
        ])->assertStatus(403);

        // 6. Tiers tente de confirmer la réalisation de la séance
        $this->postJson("/api/reservations/{$reservation->id}/confirmer")->assertStatus(403);

        // 7. Tiers tente d'ouvrir un litige
        $this->postJson("/api/reservations/{$reservation->id}/litige", [
            'description' => 'Tentative d ouverture de litige illégitime',
        ])->assertStatus(403);
    }

    public function test_idor_protection_sur_les_evaluations_tiers_non_participant(): void
    {
        $intervenant = $this->createUser();
        $beneficiaire = $this->createUser();
        $tiers = $this->createUser();
        $categorie = Categorie::create(['nom' => 'Langues']);

        $offre = (new Offre())->forceFill([
            'etudiant_id' => $intervenant->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Espagnol conversationnel',
            'competence' => 'Espagnol',
            'description' => 'Pratique orale pour perfectionner son expression fluide.',
            'niveau' => 'intermediaire',
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
            'lieu_public' => 'Cafétéria',
            'lien_ou_lieu_prive' => 'Table ronde du fond',
        ]);

        $reservation = $this->createReservation([
            'creneau_id' => $creneau->id,
            'beneficiaire_id' => $beneficiaire->id,
            'statut' => 'realisee',
            'cout_credits' => 1,
            'confirmation_intervenant' => true,
            'confirmation_beneficiaire' => true,
        ]);

        Sanctum::actingAs($tiers);

        // Un tiers tente de déposer une évaluation pour une séance à laquelle il n'a pas participé
        $this->postJson('/api/evaluations', [
            'reservation_id' => $reservation->id,
            'note' => 5,
            'commentaire' => 'Faux avis déposé par un tiers',
        ])->assertStatus(403);
    }
}
