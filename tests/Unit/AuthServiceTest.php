<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Portefeuille;
use App\Models\MouvementCredit;
use App\Services\CreditService;
use App\Services\AuthService;
use App\Exceptions\OperationFinanciereException;
use App\Exceptions\CompteDesactiveException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthServiceTest extends TestCase
{
    protected CreditService $creditService;
    protected AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createTestSchema();

        $this->creditService = new CreditService();
        $this->authService = new AuthService($this->creditService);
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

        Schema::create('mouvements_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portefeuille_id')->constrained('portefeuilles');
            $table->foreignId('reservation_id')->nullable();
            $table->timestamp('date_mouvement')->nullable();
            $table->integer('quantite');
            $table->string('type');
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

    /**
     * TEST 1 : L'inscription crée l'utilisateur avec rôle étudiant et statut actif (RG01, RG18).
     */
    public function test_inscription_cree_etudiant_actif(): void
    {
        $result = $this->authService->register([
            'nom' => 'Tazi',
            'prenom' => 'Salma',
            'email' => 'salma.tazi@estbm.ac.ma',
            'password' => 'Password123!',
            'filiere' => 'Génie Logiciel',
            'competences_proposees' => 'Python',
            'competences_recherchees' => 'Laravel',
        ]);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals('Bearer', $result['token_type']);

        $user = User::where('email', 'salma.tazi@estbm.ac.ma')->first();
        $this->assertNotNull($user);
        $this->assertEquals('etudiant', $user->role);
        $this->assertEquals('actif', $user->statut);
        $this->assertTrue(Hash::check('Password123!', $user->password));
    }

    /**
     * TEST 2 : L'inscription attribue exactement 2 crédits de bienvenue (RG01).
     */
    public function test_inscription_attribue_deux_credits_bienvenue(): void
    {
        $this->authService->register([
            'nom' => 'Idrissi',
            'prenom' => 'Youssef',
            'email' => 'youssef@estbm.ac.ma',
            'password' => 'Password123!',
        ]);

        $user = User::where('email', 'youssef@estbm.ac.ma')->first();
        $solde = $this->creditService->getSolde($user);

        $this->assertEquals(2, $solde['solde_disponible']);
        $this->assertEquals(0, $solde['credits_bloques']);
        $this->assertEquals(2, $solde['solde_total']);

        // Vérification de la traçabilité immuable (RG16)
        $mvt = MouvementCredit::where('type', 'bienvenue')->first();
        $this->assertNotNull($mvt);
        $this->assertEquals(2, $mvt->quantite);
    }

    /**
     * TEST 3 : L'inscription refuse un email déjà utilisé.
     */
    public function test_inscription_refuse_email_existant(): void
    {
        $this->authService->register([
            'nom' => 'Premier',
            'prenom' => 'User',
            'email' => 'doublon@estbm.ac.ma',
            'password' => 'Password123!',
        ]);

        $this->expectException(ValidationException::class);
        $this->authService->register([
            'nom' => 'Deuxieme',
            'prenom' => 'User',
            'email' => 'doublon@estbm.ac.ma',
            'password' => 'AnotherPassword123!',
        ]);
    }

    /**
     * TEST 4 : Le rôle et le statut ne peuvent pas être surchargés lors de l'inscription.
     */
    public function test_role_et_statut_ignores_lors_inscription(): void
    {
        $this->authService->register([
            'nom' => 'Pirate',
            'prenom' => 'Admin',
            'email' => 'pirate@estbm.ac.ma',
            'password' => 'Password123!',
            'role' => 'administrateur',
            'statut' => 'desactive',
        ]);

        $user = User::where('email', 'pirate@estbm.ac.ma')->first();
        $this->assertEquals('etudiant', $user->role);
        $this->assertEquals('actif', $user->statut);
    }

    /**
     * TEST 5 : Atomicité : Rollback intégral si l'attribution des crédits échoue.
     */
    public function test_atomicite_rollback_si_attribution_credits_echoue(): void
    {
        $failingCreditService = new class extends CreditService {
            public function attribuerCreditsBienvenue(User $etudiant): \App\Models\MouvementCredit
            {
                throw new OperationFinanciereException("Erreur simulée lors du crédit.");
            }
        };

        $service = new AuthService($failingCreditService);

        try {
            $service->register([
                'nom' => 'Erreur',
                'prenom' => 'Test',
                'email' => 'erreur@estbm.ac.ma',
                'password' => 'Password123!',
            ]);
            $this->fail("L'inscription aurait dû échouer.");
        } catch (OperationFinanciereException $e) {
            // Exception interceptée
        }

        // Aucune donnée orpheline en base
        $this->assertNull(User::where('email', 'erreur@estbm.ac.ma')->first());
        $this->assertEquals(0, Portefeuille::count());
        $this->assertEquals(0, MouvementCredit::count());
        $this->assertEquals(0, PersonalAccessToken::count());
    }

    /**
     * TEST 6 : Connexion réussie avec identifiants valides.
     */
    public function test_connexion_reussie(): void
    {
        $this->authService->register([
            'nom' => 'Client',
            'prenom' => 'Test',
            'email' => 'client@estbm.ac.ma',
            'password' => 'MonPass123!',
        ]);

        $result = $this->authService->login([
            'email' => 'client@estbm.ac.ma',
            'password' => 'MonPass123!',
        ]);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals('client@estbm.ac.ma', $result['user']['email']);
        $this->assertEquals(2, $result['user']['portefeuille']['solde_disponible']);
    }

    /**
     * TEST 7 : Connexion échoue si le mot de passe est incorrect (message générique).
     */
    public function test_connexion_echoue_mot_de_passe_incorrect(): void
    {
        $this->authService->register([
            'nom' => 'Secret',
            'prenom' => 'User',
            'email' => 'secret@estbm.ac.ma',
            'password' => 'BonMotDePasse123!',
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Les identifiants fournis sont incorrects.");
        $this->authService->login([
            'email' => 'secret@estbm.ac.ma',
            'password' => 'MauvaisMotDePasse!',
        ]);
    }

    /**
     * TEST 8 : Connexion échoue si l'email n'existe pas (même message générique).
     */
    public function test_connexion_echoue_email_inexistant(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Les identifiants fournis sont incorrects.");
        $this->authService->login([
            'email' => 'inconnu@estbm.ac.ma',
            'password' => 'Password123!',
        ]);
    }

    /**
     * TEST 9 : Connexion refusée pour un compte désactivé (RG18).
     */
    public function test_connexion_refusee_compte_desactive(): void
    {
        $this->authService->register([
            'nom' => 'Bloque',
            'prenom' => 'User',
            'email' => 'bloque@estbm.ac.ma',
            'password' => 'Password123!',
        ]);

        $user = User::where('email', 'bloque@estbm.ac.ma')->first();
        $user->statut = 'desactive';
        $user->save();

        $this->expectException(CompteDesactiveException::class);
        $this->expectExceptionMessage("Votre compte a été désactivé par un administrateur (RG18)");
        $this->authService->login([
            'email' => 'bloque@estbm.ac.ma',
            'password' => 'Password123!',
        ]);
    }

    /**
     * TEST 10 : Déconnexion révoque le token d'accès.
     */
    public function test_deconnexion_revoque_token(): void
    {
        $this->authService->register([
            'nom' => 'Logout',
            'prenom' => 'User',
            'email' => 'logout.user@estbm.ac.ma',
            'password' => 'Password123!',
        ]);

        $user = User::where('email', 'logout.user@estbm.ac.ma')->first();
        $token = $user->createToken('test_token');

        // Simuler le token comme étant le token courant
        $user->withAccessToken($token->accessToken);

        // Le compte possède déjà 1 token issu de register(), plus 1 nouveau token créé
        $this->assertEquals(2, PersonalAccessToken::count());

        $loggedOut = $this->authService->logout($user);
        $this->assertTrue($loggedOut);

        // Le token courant spécifique a bien été supprimé
        $this->assertEquals(1, PersonalAccessToken::count());
        $this->assertNull(PersonalAccessToken::find($token->accessToken->id));
    }

    /**
     * TEST 11 : La méthode me() renvoie les informations publiques et le solde en temps réel.
     */
    public function test_me_renvoie_profil_et_solde_temps_reel(): void
    {
        $this->authService->register([
            'nom' => 'Me',
            'prenom' => 'User',
            'email' => 'me.user@estbm.ac.ma',
            'password' => 'Password123!',
        ]);

        $user = User::where('email', 'me.user@estbm.ac.ma')->first();
        $profile = $this->authService->me($user);

        $this->assertArrayHasKey('user', $profile);
        $this->assertEquals('me.user@estbm.ac.ma', $profile['user']['email']);
        $this->assertEquals(2, $profile['user']['portefeuille']['solde_disponible']);
        $this->assertEquals(0, $profile['user']['portefeuille']['credits_bloques']);
        $this->assertEquals(2, $profile['user']['portefeuille']['solde_total']);
    }

    /**
     * TEST 12 : Les attributs sensibles ne sont jamais exposés dans la réponse utilisateur.
     */
    public function test_attributs_sensibles_non_exposes(): void
    {
        $this->authService->register([
            'nom' => 'Safe',
            'prenom' => 'User',
            'email' => 'safe@estbm.ac.ma',
            'password' => 'SecretPassword123!',
        ]);

        $user = User::where('email', 'safe@estbm.ac.ma')->first();
        $response = $this->authService->formatUserResponse($user);

        $this->assertArrayNotHasKey('password', $response);
        $this->assertArrayNotHasKey('remember_token', $response);
        $this->assertArrayNotHasKey('deleted_at', $response);
    }

    /**
     * TEST 13 : formatUserResponse() ne masque JAMAIS une erreur financière par un solde fictif.
     */
    public function test_format_user_response_ne_retourne_jamais_de_solde_fictif(): void
    {
        $user = User::create([
            'nom' => 'Orphelin',
            'prenom' => 'User',
            'email' => 'orphelin@estbm.ac.ma',
            'password' => Hash::make('Password123!'),
        ]);

        // Sans portefeuille créé, formatUserResponse doit lever PortefeuilleIntrouvableException
        // et non pas retourner [solde_disponible => 0]
        $this->expectException(\App\Exceptions\PortefeuilleIntrouvableException::class);
        $this->authService->formatUserResponse($user);
    }

    /**
     * TEST 14 : Déconnexion avec token Sanctum ne supprime que le token courant.
     */
    public function test_logout_ne_revoque_que_le_token_courant_sans_toucher_aux_autres(): void
    {
        $user = User::create([
            'nom' => 'User',
            'prenom' => 'TwoTokens',
            'email' => 'two.tokens@estbm.ac.ma',
            'password' => Hash::make('Password123!'),
        ]);

        $token1 = $user->createToken('Token1');
        $token2 = $user->createToken('Token2');

        $this->assertEquals(2, PersonalAccessToken::where('tokenable_id', $user->id)->count());

        // L'utilisateur est connecté avec Token1
        $user->withAccessToken($token1->accessToken);

        $this->authService->logout($user);

        // Seul Token1 a été supprimé, Token2 est toujours présent
        $this->assertEquals(1, PersonalAccessToken::where('tokenable_id', $user->id)->count());
        $this->assertNull(PersonalAccessToken::find($token1->accessToken->id));
        $this->assertNotNull(PersonalAccessToken::find($token2->accessToken->id));
    }

    /**
     * TEST 15 : En cas de violation de contrainte UNIQUE SQL concurrente, une ValidationException est levée.
     */
    public function test_gestion_concurrente_violation_unique_sql_levee_comme_validation_exception(): void
    {
        User::create([
            'nom' => 'Existant',
            'prenom' => 'User',
            'email' => 'concurrence.sql@estbm.ac.ma',
            'password' => Hash::make('Password123!'),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Cette adresse électronique est déjà associée à un compte existant (RG01).");

        $this->authService->register([
            'nom' => 'Autre',
            'prenom' => 'User',
            'email' => 'concurrence.sql@estbm.ac.ma',
            'password' => 'Password123!',
        ]);
    }

    /**
     * TEST 16 : isDuplicateEmailException() distingue rigoureusement un doublon d'email d'une violation FK ou NOT NULL.
     */
    public function test_16_distinction_stricte_entre_doublon_email_et_autre_erreur_sql(): void
    {
        // 1. MySQL 1062 sur users_email_unique -> Doit être identifié comme doublon email
        $pdoExceptionMysqlEmail = new \PDOException("Duplicate entry 'test@estbm.ac.ma' for key 'users_email_unique'");
        $eMysqlEmail = new \Illuminate\Database\QueryException('mysql', 'insert into users...', [], $pdoExceptionMysqlEmail);
        $eMysqlEmail->errorInfo = ['23000', 1062, "Duplicate entry 'test@estbm.ac.ma' for key 'users_email_unique'"];
        $this->assertTrue($this->authService->isDuplicateEmailException($eMysqlEmail));

        // 2. SQLite sur users.email -> Doit être identifié comme doublon email
        $pdoExceptionSqliteEmail = new \PDOException("UNIQUE constraint failed: users.email");
        $eSqliteEmail = new \Illuminate\Database\QueryException('sqlite', 'insert into users...', [], $pdoExceptionSqliteEmail);
        $eSqliteEmail->errorInfo = ['23000', 19, "UNIQUE constraint failed: users.email"];
        $this->assertTrue($this->authService->isDuplicateEmailException($eSqliteEmail));

        // 3. MySQL 1452 : Violation de clé étrangère -> Ne doit JAMAIS être considéré comme doublon email
        $pdoExceptionFk = new \PDOException("Cannot add or update a child row: a foreign key constraint fails (`skillswap`.`reservations`, CONSTRAINT `fk_creneau` FOREIGN KEY (`creneau_id`) REFERENCES `creneaux` (`id`))");
        $eFk = new \Illuminate\Database\QueryException('mysql', 'insert into reservations...', [], $pdoExceptionFk);
        $eFk->errorInfo = ['23000', 1452, "foreign key constraint fails"];
        $this->assertFalse($this->authService->isDuplicateEmailException($eFk));

        // 4. SQLite : Violation de clé étrangère -> Ne doit JAMAIS être considéré comme doublon email
        $pdoExceptionSqliteFk = new \PDOException("FOREIGN KEY constraint failed");
        $eSqliteFk = new \Illuminate\Database\QueryException('sqlite', 'insert into reservations...', [], $pdoExceptionSqliteFk);
        $eSqliteFk->errorInfo = ['23000', 19, "FOREIGN KEY constraint failed"];
        $this->assertFalse($this->authService->isDuplicateEmailException($eSqliteFk));

        // 5. NOT NULL constraint failed -> Ne doit pas être un doublon email
        $pdoExceptionNotNull = new \PDOException("Column 'nom' cannot be null");
        $eNotNull = new \Illuminate\Database\QueryException('mysql', 'insert into users...', [], $pdoExceptionNotNull);
        $eNotNull->errorInfo = ['23000', 1048, "Column 'nom' cannot be null"];
        $this->assertFalse($this->authService->isDuplicateEmailException($eNotNull));

        // 6. Doublon sur une autre table (ex: categories.nom) -> Ne doit pas être un doublon users.email
        $pdoExceptionCat = new \PDOException("Duplicate entry 'Musique' for key 'categories_nom_unique'");
        $eCat = new \Illuminate\Database\QueryException('mysql', 'insert into categories...', [], $pdoExceptionCat);
        $eCat->errorInfo = ['23000', 1062, "Duplicate entry 'Musique' for key 'categories_nom_unique'"];
        $this->assertFalse($this->authService->isDuplicateEmailException($eCat));

        // 7. Doublon sur une autre table d'utilisateurs (ex: admins_email_unique) -> Ne doit pas être users_email_unique
        $pdoExceptionAdmin = new \PDOException("Duplicate entry 'admin@estbm.ac.ma' for key 'admins_email_unique'");
        $eAdmin = new \Illuminate\Database\QueryException('mysql', 'insert into admins...', [], $pdoExceptionAdmin);
        $eAdmin->errorInfo = ['23000', 1062, "Duplicate entry 'admin@estbm.ac.ma' for key 'admins_email_unique'"];
        $this->assertFalse($this->authService->isDuplicateEmailException($eAdmin));
    }

    /**
     * TEST 17 : Une violation de clé étrangère remonte normalement en QueryException sans faux message d'email dupliqué.
     */
    public function test_17_violation_cle_etrangere_remonte_en_query_exception(): void
    {
        // Mock ou faux CreditService qui provoque une QueryException de clé étrangère
        $failingFkService = new class extends CreditService {
            public function attribuerCreditsBienvenue(User $etudiant): \App\Models\MouvementCredit
            {
                $pdoEx = new \PDOException("FOREIGN KEY constraint failed");
                $queryEx = new \Illuminate\Database\QueryException('sqlite', 'insert into...', [], $pdoEx);
                $queryEx->errorInfo = ['23000', 19, 'FOREIGN KEY constraint failed'];
                throw $queryEx;
            }
        };

        $service = new AuthService($failingFkService);

        // Doit remonter comme QueryException et NON pas comme ValidationException
        $this->expectException(\Illuminate\Database\QueryException::class);
        $service->register([
            'nom' => 'FK',
            'prenom' => 'Test',
            'email' => 'fk.test@estbm.ac.ma',
            'password' => 'Password123!',
        ]);
    }
}
