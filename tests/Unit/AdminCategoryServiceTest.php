<?php

namespace Tests\Unit;

use App\Exceptions\CategorieNonSupprimableException;
use App\Models\Categorie;
use App\Models\Offre;
use App\Models\User;
use App\Services\AdminCategoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminCategoryServiceTest extends TestCase
{
    protected AdminCategoryService $categoryService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createTestSchema();

        $this->categoryService = new AdminCategoryService();
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
    }

    /**
     * Test 1 : Liste des catégories avec nombre d'offres
     */
    public function test_lister_categories_avec_nombre_offres(): void
    {
        $cat1 = Categorie::create(['nom' => 'Musique']);
        $cat2 = Categorie::create(['nom' => 'Design']);

        $user = new User();
        $user->forceFill([
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'cat_user@univ.fr',
            'password' => Hash::make('password'),
        ])->save();

        $offre = new Offre();
        $offre->forceFill([
            'etudiant_id' => $user->id,
            'categorie_id' => $cat1->id,
            'titre' => 'Guitare',
            'competence' => 'Guitare',
            'description' => 'Cours de guitare',
            'duree' => 1,
            'statut' => 'publiee',
        ])->save();

        $liste = $this->categoryService->listerCategories();

        $this->assertEquals(2, $liste->count());
        $this->assertEquals('Design', $liste->first()->nom); // Trié alphabétiquement
        $this->assertEquals(1, $liste->where('nom', 'Musique')->first()->offres_count);
        $this->assertEquals(0, $liste->where('nom', 'Design')->first()->offres_count);
    }

    /**
     * Test 2 : Création d'une catégorie
     */
    public function test_creation_categorie_reussie(): void
    {
        $cat = $this->categoryService->creerCategorie(['nom' => 'Sciences Politiques']);

        $this->assertDatabaseHas('categories', ['nom' => 'Sciences Politiques']);
        $this->assertEquals('Sciences Politiques', $cat->nom);
    }

    /**
     * Test 3 : Modification d'une catégorie
     */
    public function test_modification_categorie_reussie(): void
    {
        $cat = Categorie::create(['nom' => 'Ancien Nom']);

        $updated = $this->categoryService->modifierCategorie($cat->id, ['nom' => 'Nouveau Nom']);

        $this->assertEquals('Nouveau Nom', $updated->nom);
        $this->assertDatabaseHas('categories', ['nom' => 'Nouveau Nom']);
    }

    /**
     * Test 4 : Suppression réussie d'une catégorie sans offre
     */
    public function test_suppression_categorie_vide_reussie(): void
    {
        $cat = Categorie::create(['nom' => 'Catégorie Orpheline']);

        $res = $this->categoryService->supprimerCategorie($cat->id);

        $this->assertTrue($res);
        $this->assertDatabaseMissing('categories', ['id' => $cat->id]);
    }

    /**
     * Test 5 : Protection stricte contre la suppression d'une catégorie utilisée (RG04)
     */
    public function test_protection_suppression_categorie_utilisee_leve_exception_409(): void
    {
        $cat = Categorie::create(['nom' => 'Informatique']);

        $user = new User();
        $user->forceFill([
            'nom' => 'Dev',
            'prenom' => 'User',
            'email' => 'dev_cat@univ.fr',
            'password' => Hash::make('password'),
        ])->save();

        $offre = new Offre();
        $offre->forceFill([
            'etudiant_id' => $user->id,
            'categorie_id' => $cat->id,
            'titre' => 'Algo',
            'competence' => 'Algo',
            'description' => 'Cours algorithmique',
            'duree' => 1,
            'statut' => 'publiee',
        ])->save();

        $this->expectException(CategorieNonSupprimableException::class);
        $this->categoryService->supprimerCategorie($cat->id);
    }
}
