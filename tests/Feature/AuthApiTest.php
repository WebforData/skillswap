<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Portefeuille;
use App\Models\MouvementCredit;
use App\Services\CreditService;
use App\Services\AuthService;
use App\Exceptions\OperationFinanciereException;
use App\Exceptions\PortefeuilleIntrouvableException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;

class AuthApiTest extends TestCase
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
     * TEST 1 : Inscription réussie via API POST /api/auth/register.
     */
    public function test_1_inscription_reussie(): void
    {
        $payload = [
            'nom' => 'Benali',
            'prenom' => 'Karim',
            'email' => 'karim.benali@estbm.ac.ma',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'filiere' => 'Génie Informatique',
            'competences_proposees' => 'Laravel, React',
            'competences_recherchees' => 'DevOps, Docker',
        ];

        $response = $this->postJson(route('api.auth.register'), $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'nom',
                    'prenom',
                    'email',
                    'role',
                    'statut',
                    'filiere',
                    'competences_proposees',
                    'competences_recherchees',
                    'portefeuille' => [
                        'solde_disponible',
                        'credits_bloques',
                        'solde_total',
                    ],
                    'created_at',
                ],
                'token',
                'token_type',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'karim.benali@estbm.ac.ma',
            'role' => 'etudiant',
            'statut' => 'actif',
        ]);

        $user = User::where('email', 'karim.benali@estbm.ac.ma')->first();
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
    }

    /**
     * TEST 2 : Email déjà utilisé refusé (RG01).
     */
    public function test_2_inscription_email_deja_utilise_refusee(): void
    {
        User::create([
            'nom' => 'Existant',
            'prenom' => 'User',
            'email' => 'existant@estbm.ac.ma',
            'password' => Hash::make('Password123!'),
        ]);

        $payload = [
            'nom' => 'Nouveau',
            'prenom' => 'Doublon',
            'email' => 'existant@estbm.ac.ma',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson(route('api.auth.register'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * TEST 3 : Mot de passe invalide (< 8 caractères ou non confirmé).
     */
    public function test_3_inscription_mot_de_passe_invalide(): void
    {
        // Cas 1 : Mot de passe trop court (< 8 caractères)
        $responseShort = $this->postJson(route('api.auth.register'), [
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'court@estbm.ac.ma',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);
        $responseShort->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Cas 2 : Confirmation incorrecte
        $responseMismatch = $this->postJson(route('api.auth.register'), [
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'mismatch@estbm.ac.ma',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);
        $responseMismatch->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    /**
     * TEST 4 : Attribution des 2 crédits de bienvenue lors de l'inscription (RG01).
     */
    public function test_4_attribution_deux_credits_bienvenue_a_inscription(): void
    {
        $payload = [
            'nom' => 'Alami',
            'prenom' => 'Mehdi',
            'email' => 'mehdi.alami@estbm.ac.ma',
            'password' => 'MehdiPass123!',
            'password_confirmation' => 'MehdiPass123!',
        ];

        $response = $this->postJson(route('api.auth.register'), $payload);
        $response->assertStatus(201);

        $user = User::where('email', 'mehdi.alami@estbm.ac.ma')->first();
        $this->assertNotNull($user);

        // Vérification du portefeuille
        $portefeuille = Portefeuille::where('etudiant_id', $user->id)->first();
        $this->assertNotNull($portefeuille);
        $this->assertEquals(2, $portefeuille->solde_disponible);
        $this->assertEquals(0, $portefeuille->credits_bloques);

        // Vérification du mouvement financier immuable
        $mouvement = MouvementCredit::where('portefeuille_id', $portefeuille->id)->first();
        $this->assertNotNull($mouvement);
        $this->assertEquals(2, $mouvement->quantite);
        $this->assertEquals('bienvenue', $mouvement->type);
    }

    /**
     * TEST 5 : Impossibilité de recevoir deux fois les crédits de bienvenue (RG01).
     */
    public function test_5_impossibilite_recevoir_deux_fois_credits_bienvenue(): void
    {
        $result = $this->authService->register([
            'nom' => 'Unique',
            'prenom' => 'Credit',
            'email' => 'unique.credit@estbm.ac.ma',
            'password' => 'Password123!',
        ]);

        $user = User::where('email', 'unique.credit@estbm.ac.ma')->first();

        // Deuxième tentative d'attribution directe -> Doit lever OperationFinanciereException
        $this->expectException(OperationFinanciereException::class);
        $this->expectExceptionMessage("déjà été attribués");
        $this->creditService->attribuerCreditsBienvenue($user);
    }

    /**
     * TEST 6 : Connexion réussie via POST /api/auth/login.
     */
    public function test_6_connexion_reussie(): void
    {
        $this->authService->register([
            'nom' => 'Test',
            'prenom' => 'Login',
            'email' => 'login.test@estbm.ac.ma',
            'password' => 'MyPassword123!',
        ]);

        $response = $this->postJson(route('api.auth.login'), [
            'email' => 'login.test@estbm.ac.ma',
            'password' => 'MyPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'email',
                    'role',
                    'statut',
                    'portefeuille',
                ],
                'token',
                'token_type',
            ]);

        $this->assertEquals('Bearer', $response->json('token_type'));
        $this->assertNotEmpty($response->json('token'));
    }

    /**
     * TEST 7 : Connexion refusée si mot de passe incorrect (message générique de sécurité).
     */
    public function test_7_connexion_mot_de_passe_incorrect(): void
    {
        $this->authService->register([
            'nom' => 'Test',
            'prenom' => 'Login',
            'email' => 'user@estbm.ac.ma',
            'password' => 'RealPassword123!',
        ]);

        // Mot de passe erroné
        $responseBadPassword = $this->postJson(route('api.auth.login'), [
            'email' => 'user@estbm.ac.ma',
            'password' => 'WrongPassword!',
        ]);

        $responseBadPassword->assertStatus(401)
            ->assertJson([
                'message' => 'Les identifiants fournis sont incorrects.',
            ]);

        // Email inexistant -> Même message générique (anti-énumération)
        $responseBadEmail = $this->postJson(route('api.auth.login'), [
            'email' => 'inexistant@estbm.ac.ma',
            'password' => 'SomePassword123!',
        ]);

        $responseBadEmail->assertStatus(401)
            ->assertJson([
                'message' => 'Les identifiants fournis sont incorrects.',
            ]);
    }

    /**
     * TEST 8 : Connexion refusée si compte désactivé (RG18).
     */
    public function test_8_connexion_compte_desactive_refusee(): void
    {
        $result = $this->authService->register([
            'nom' => 'Inactif',
            'prenom' => 'User',
            'email' => 'desactive@estbm.ac.ma',
            'password' => 'MyPassword123!',
        ]);

        // Désactivation du compte par un administrateur (RG18)
        $user = User::where('email', 'desactive@estbm.ac.ma')->first();
        $user->statut = 'desactive';
        $user->save();

        $response = $this->postJson(route('api.auth.login'), [
            'email' => 'desactive@estbm.ac.ma',
            'password' => 'MyPassword123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Votre compte a été désactivé par un administrateur (RG18). Veuillez contacter le support.',
            ]);
    }

    /**
     * TEST 9 : Déconnexion révoque le token Sanctum actuel.
     */
    public function test_9_deconnexion_revoque_token(): void
    {
        $registerRes = $this->authService->register([
            'nom' => 'Logout',
            'prenom' => 'Test',
            'email' => 'logout@estbm.ac.ma',
            'password' => 'Password123!',
        ]);

        $token = $registerRes['token'];

        // Accès avec le token -> 200 OK
        $meResponseBefore = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.auth.me'));
        $meResponseBefore->assertStatus(200);

        // Appel de déconnexion POST /api/auth/logout
        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson(route('api.auth.logout'));

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Déconnexion réussie. Le token a été révoqué.',
            ]);

        // Vérification de la révocation du token en base
        $this->assertEquals(0, PersonalAccessToken::count());

        // Réinitialiser le guard mémoire de test pour forcer la ré-authentification par token
        app('auth')->forgetGuards();

        // Nouvel appel avec le token révoqué -> 401 Unauthorized
        $meResponseAfter = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.auth.me'));
        $meResponseAfter->assertStatus(401);
    }

    /**
     * TEST 10 : Accès au profil /me avec et sans token Sanctum.
     */
    public function test_10_acces_me_avec_et_sans_token(): void
    {
        // 1. Sans token -> Doit être rejeté 401 Unauthorized
        $responseWithoutToken = $this->getJson(route('api.auth.me'));
        $responseWithoutToken->assertStatus(401);

        // 2. Avec token valide -> 200 OK avec informations du profil et portefeuille
        $registerRes = $this->authService->register([
            'nom' => 'Profile',
            'prenom' => 'User',
            'email' => 'profile@estbm.ac.ma',
            'password' => 'Password123!',
        ]);
        $token = $registerRes['token'];

        $responseWithToken = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson(route('api.auth.me'));

        $responseWithToken->assertStatus(200)
            ->assertJson([
                'user' => [
                    'email' => 'profile@estbm.ac.ma',
                    'role' => 'etudiant',
                    'statut' => 'actif',
                    'portefeuille' => [
                        'solde_disponible' => 2,
                        'credits_bloques' => 0,
                        'solde_total' => 2,
                    ],
                ],
            ]);
    }

    /**
     * TEST 11 : Le rôle ne peut jamais être forcé ou choisi lors de l'inscription.
     */
    public function test_11_role_ne_peut_pas_etre_choisi_a_inscription(): void
    {
        $payload = [
            'nom' => 'Hacker',
            'prenom' => 'Admin',
            'email' => 'hacker@estbm.ac.ma',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'administrateur', // Tentative d'élévation de privilèges
            'statut' => 'banni',
        ];

        $response = $this->postJson(route('api.auth.register'), $payload);
        $response->assertStatus(201);

        $user = User::where('email', 'hacker@estbm.ac.ma')->first();
        $this->assertEquals('etudiant', $user->role, "Le rôle DOIT impérativement être 'etudiant'.");
        $this->assertEquals('actif', $user->statut, "Le statut DOIT impérativement être 'actif'.");
    }

    /**
     * TEST 12 : Atomicité ACID : Rollback intégral si l'attribution des crédits échoue.
     */
    public function test_12_rollback_integral_si_attribution_credits_echoue(): void
    {
        $failingCreditService = new class extends CreditService {
            public function attribuerCreditsBienvenue(User $etudiant): \App\Models\MouvementCredit
            {
                throw new OperationFinanciereException("Erreur simulée lors du crédit bancaire/financier.");
            }
        };

        $authServiceWithFailure = new AuthService($failingCreditService);

        try {
            $authServiceWithFailure->register([
                'nom' => 'Fail',
                'prenom' => 'User',
                'email' => 'fail@estbm.ac.ma',
                'password' => 'Password123!',
            ]);
            $this->fail("L'inscription aurait dû échouer et déclencher un rollback.");
        } catch (OperationFinanciereException $e) {
            $this->assertEquals("Erreur simulée lors du crédit bancaire/financier.", $e->getMessage());
        }

        // Vérification de l'atomicité : AUCUN utilisateur ni portefeuille n'a été conservé en base
        $this->assertDatabaseMissing('users', ['email' => 'fail@estbm.ac.ma']);
        $this->assertEquals(0, Portefeuille::count());
        $this->assertEquals(0, MouvementCredit::count());
        $this->assertEquals(0, PersonalAccessToken::count());
    }

    /**
     * TEST 13 : Rate Limiter anti-bruteforce retourne HTTP 429 après tentatives excessives.
     */
    public function test_13_rate_limiting_retourne_429_apres_tentatives_excessives(): void
    {
        RateLimiter::clear('ratelimit@estbm.ac.ma|127.0.0.1');

        $credentials = [
            'email' => 'ratelimit@estbm.ac.ma',
            'password' => 'WrongPassword!',
        ];

        // 5 tentatives infructueuses consécutives -> HTTP 401
        for ($i = 1; $i <= 5; $i++) {
            $res = $this->postJson(route('api.auth.login'), $credentials);
            $res->assertStatus(401);
        }

        // 6ème tentative -> Bloquée par le Rate Limiter : HTTP 429 Too Many Requests
        $resRateLimited = $this->postJson(route('api.auth.login'), $credentials);
        $resRateLimited->assertStatus(429)
            ->assertJsonStructure(['message']);

        $this->assertStringContainsString('Trop de tentatives de connexion', $resRateLimited->json('message'));
    }

    /**
     * TEST 14 : Réinitialisation du Rate Limiter après une connexion réussie.
     */
    public function test_14_reinitialisation_rate_limiter_apres_connexion_reussie(): void
    {
        $this->authService->register([
            'nom' => 'Reset',
            'prenom' => 'RateLimit',
            'email' => 'reset.rl@estbm.ac.ma',
            'password' => 'CorrectPass123!',
        ]);

        $throttleKey = 'reset.rl@estbm.ac.ma|127.0.0.1';
        RateLimiter::clear($throttleKey);

        // 2 échecs préalables
        for ($i = 1; $i <= 2; $i++) {
            $this->postJson(route('api.auth.login'), [
                'email' => 'reset.rl@estbm.ac.ma',
                'password' => 'BadPass!',
            ])->assertStatus(401);
        }

        // Connexion réussie -> Déclenche clearRateLimiter()
        $successRes = $this->postJson(route('api.auth.login'), [
            'email' => 'reset.rl@estbm.ac.ma',
            'password' => 'CorrectPass123!',
        ]);
        $successRes->assertStatus(200);

        // Vérification que le RateLimiter a bien été réinitialisé à 0 tentative
        $this->assertEquals(0, RateLimiter::attempts($throttleKey));
    }

    /**
     * TEST 15 : Absence de solde fictif en cas d'erreur financière.
     * formatUserResponse() ne masque JAMAIS une anomalie de portefeuille par un solde fictif à 0.
     */
    public function test_15_absence_de_solde_fictif_en_cas_derreur_financiere(): void
    {
        $user = User::create([
            'nom' => 'Sans',
            'prenom' => 'Portefeuille',
            'email' => 'sans.portefeuille@estbm.ac.ma',
            'password' => Hash::make('Password123!'),
        ]);

        // Aucun portefeuille n'a été créé pour cet utilisateur en base
        $this->expectException(PortefeuilleIntrouvableException::class);
        $this->authService->formatUserResponse($user);
    }

    /**
     * TEST 16 : Deux inscriptions concurrentes avec le même email (Violation contrainte UNIQUE SQL).
     * Vérifie le traitement propre sans exposition de traces SQL et retour en HTTP 422.
     */
    public function test_16_inscriptions_simultanees_violation_unique_sql_retourne_422(): void
    {
        // 1. Première inscription en base
        User::create([
            'nom' => 'First',
            'prenom' => 'User',
            'email' => 'concurrent@estbm.ac.ma',
            'password' => Hash::make('Password123!'),
        ]);

        // 2. Deuxième inscription concurrente tentant d'insérer le même email
        $response = $this->postJson(route('api.auth.register'), [
            'nom' => 'Second',
            'prenom' => 'User',
            'email' => 'concurrent@estbm.ac.ma',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        // Doit renvoyer HTTP 422 avec message RG01 propre sans trace SQLSTATE ou QueryException
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertStringNotContainsString('SQLSTATE', json_encode($response->json()));
        $this->assertStringNotContainsString('QueryException', json_encode($response->json()));
    }

    /**
     * TEST 17 : Déconnexion révoque UNIQUEMENT le token courant et préserve les autres tokens actifs.
     */
    public function test_17_deconnexion_revoque_uniquement_le_token_courant(): void
    {
        $user = User::create([
            'nom' => 'Multi',
            'prenom' => 'Device',
            'email' => 'multidevice@estbm.ac.ma',
            'password' => Hash::make('Password123!'),
        ]);

        // Création du portefeuille avec 2 crédits
        $portefeuille = new Portefeuille(['etudiant_id' => $user->id]);
        $portefeuille->solde_disponible = 2;
        $portefeuille->credits_bloques = 0;
        $portefeuille->save();

        // Création de 2 tokens distincts (ex: session PC et session Mobile)
        $tokenPC = $user->createToken('PC_Token')->plainTextToken;
        $tokenMobile = $user->createToken('Mobile_Token')->plainTextToken;

        $this->assertEquals(2, PersonalAccessToken::where('tokenable_id', $user->id)->count());

        // Déconnexion avec le token PC
        $logoutPC = $this->withHeader('Authorization', "Bearer {$tokenPC}")
            ->postJson(route('api.auth.logout'));
        $logoutPC->assertStatus(200);

        // Vérification : il reste exactement 1 token en base
        $this->assertEquals(1, PersonalAccessToken::where('tokenable_id', $user->id)->count());

        app('auth')->forgetGuards();

        // 1. Le token PC est révoqué -> HTTP 401
        $checkPC = $this->withHeader('Authorization', "Bearer {$tokenPC}")
            ->getJson(route('api.auth.me'));
        $checkPC->assertStatus(401);

        app('auth')->forgetGuards();

        // 2. Le token Mobile reste actif et valide -> HTTP 200 OK
        $checkMobile = $this->withHeader('Authorization', "Bearer {$tokenMobile}")
            ->getJson(route('api.auth.me'));
        $checkMobile->assertStatus(200)
            ->assertJson([
                'user' => [
                    'email' => 'multidevice@estbm.ac.ma',
                ],
            ]);
    }

    /**
     * TEST 18 : Normalisation de l'email (espaces et casse) partageant la même clé Rate Limiter.
     */
    public function test_18_rate_limiting_normalise_espaces_et_casse_meme_cle(): void
    {
        $throttleKey = 'space.case@estbm.ac.ma|127.0.0.1';
        RateLimiter::clear($throttleKey);

        // 2 tentatives avec espaces avant et après
        for ($i = 1; $i <= 2; $i++) {
            $this->postJson(route('api.auth.login'), [
                'email' => '   space.case@estbm.ac.ma   ',
                'password' => 'WrongPass!',
            ])->assertStatus(401);
        }

        // 3 tentatives en MAJUSCULES
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson(route('api.auth.login'), [
                'email' => 'SPACE.CASE@ESTBM.AC.MA',
                'password' => 'WrongPass!',
            ])->assertStatus(401);
        }

        // 6ème tentative avec email standardisé -> DOIT déclencher HTTP 429
        $response429 = $this->postJson(route('api.auth.login'), [
            'email' => 'space.case@estbm.ac.ma',
            'password' => 'WrongPass!',
        ]);

        $response429->assertStatus(429);
        $this->assertStringContainsString('Trop de tentatives de connexion', $response429->json('message'));
    }

    /**
     * TEST 19 : Une violation SQL étrangère ne doit JAMAIS afficher de faux message de doublon email.
     */
    public function test_19_violation_cle_etrangere_ne_retourne_pas_faux_doublon_email(): void
    {
        $failingFkService = new class extends CreditService {
            public function attribuerCreditsBienvenue(User $etudiant): \App\Models\MouvementCredit
            {
                $pdoEx = new \PDOException("Cannot add or update a child row: a foreign key constraint fails");
                $queryEx = new \Illuminate\Database\QueryException('mysql', 'insert into...', [], $pdoEx);
                $queryEx->errorInfo = ['23000', 1452, "Cannot add or update a child row: a foreign key constraint fails"];
                throw $queryEx;
            }
        };

        $service = new AuthService($failingFkService);

        // Doit remonter en QueryException et NON pas en ValidationException (qui donnerait un faux message de doublon)
        $this->expectException(\Illuminate\Database\QueryException::class);
        $service->register([
            'nom' => 'FK',
            'prenom' => 'Test',
            'email' => 'fk.test@estbm.ac.ma',
            'password' => 'Password123!',
        ]);
    }

    /**
     * TEST 20 : Simulation unitaire de détection de collision MySQL 1062 sur users.email (InnoDB).
     * Note d'audit : Ce test est une simulation d'exception (mock) validant l'interception de l'erreur
     * SQLSTATE 23000 / code MySQL 1062 et de la contrainte 'users_email_unique'.
     */
    public function test_20_simulation_unitaire_detection_collision_mysql_1062(): void
    {
        $pdoEx = new \PDOException("Duplicate entry 'test.mysql@estbm.ac.ma' for key 'users_email_unique'");
        $queryEx = new \Illuminate\Database\QueryException('mysql', 'insert into users...', [], $pdoEx);
        $queryEx->errorInfo = ['23000', 1062, "Duplicate entry 'test.mysql@estbm.ac.ma' for key 'users_email_unique'"];

        // Doit identifier la collision comme étant un doublon d'email users
        $this->assertTrue($this->authService->isDuplicateEmailException($queryEx));

        // En revanche, une erreur 1062 sur une autre table (ex: categories) ne doit pas être prise pour users.email
        $pdoExCat = new \PDOException("Duplicate entry 'Design' for key 'categories_nom_unique'");
        $queryExCat = new \Illuminate\Database\QueryException('mysql', 'insert into categories...', [], $pdoExCat);
        $queryExCat->errorInfo = ['23000', 1062, "Duplicate entry 'Design' for key 'categories_nom_unique'"];

        $this->assertFalse($this->authService->isDuplicateEmailException($queryExCat));
    }
}
