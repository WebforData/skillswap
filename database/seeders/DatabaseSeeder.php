<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\User;
use App\Services\AuthService;
use App\Services\CreneauService;
use App\Services\OffreService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect(['Informatique & Programmation', 'Langues & Communication', 'Mathématiques & Sciences', 'Méthodologie & Soft Skills', 'Design & Multimédia'])
            ->map(fn ($nom) => Categorie::firstOrCreate(['nom' => $nom]));
        if (! User::where('email', 'admin@skillswap.edu')->exists()) {
            $admin = new User;
            $admin->forceFill(['nom' => 'Admin', 'prenom' => 'Super', 'email' => 'admin@skillswap.edu', 'password' => Hash::make('password'), 'role' => 'administrateur', 'statut' => 'actif'])->save();
            $admin->portefeuille()->create([]);
        }
        foreach ([['Sara', 'Alami', 'sara', 'Python', 'Anglais'], ['Amine', 'Benali', 'amine', 'SQL', 'Python'], ['Yassine', 'Chraibi', 'yassine', 'Anglais', 'SQL']] as $index => [$prenom, $nom, $login, $skill, $wanted]) {
            $email = "$login@skillswap.edu";
            if (User::where('email', $email)->exists()) {
                continue;
            }
            $user = app(AuthService::class)->register(['nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'password' => 'password', 'filiere' => 'DUT Génie Informatique', 'competences_proposees' => $skill, 'competences_recherchees' => $wanted])['user'];
            $user = User::findOrFail($user['id']);
            $offre = app(OffreService::class)->creerOffre($user, ['categorie_id' => $categories[$index === 2 ? 1 : 0]->id, 'titre' => "Initiation à $skill", 'competence' => $skill, 'description' => "Une séance pratique pour découvrir $skill et progresser ensemble.", 'duree' => 1, 'mode' => 'presentiel', 'niveau' => 'debutant']);
            app(OffreService::class)->publierOffre($user, $offre->id);
            app(CreneauService::class)->ajouterCreneau($user, $offre->id, ['date' => now()->addDays(2)->toDateString(), 'heure_debut' => sprintf('%02d:00', 10 + $index * 2), 'heure_fin' => sprintf('%02d:00', 11 + $index * 2), 'lieu_public' => 'Campus EST', 'lien_ou_lieu_prive' => 'Salle B204']);
        }
    }
}
