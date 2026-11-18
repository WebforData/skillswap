<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Skill;
use App\Models\UserSkill;
use App\Models\Offer;
use App\Models\Slot;
use App\Models\CreditTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Catégories universitaires
        $categoriesData = [
            [
                'nom' => 'Informatique & Programmation',
                'slug' => 'informatique-programmation',
                'description' => 'Développement web, algorithmique, bases de données, langages C/Python/Java.',
                'icon' => 'bi-code-slash',
                'skills' => ['Python', 'PHP / Laravel', 'JavaScript', 'SQL & BDD', 'Algorithmique & C', 'Java / POO']
            ],
            [
                'nom' => 'Langues & Communication',
                'slug' => 'langues-communication',
                'description' => 'Anglais professionnel, français écrit, préparation aux entretiens et TOEIC.',
                'icon' => 'bi-translate',
                'skills' => ['Anglais technique', 'Français de communication', 'Espagnol débutant', 'Présentation orale']
            ],
            [
                'nom' => 'Mathématiques & Sciences',
                'slug' => 'mathematiques-sciences',
                'description' => 'Algèbre linéaire, analyse, probabilités et statistiques, physique appliquée.',
                'icon' => 'bi-calculator',
                'skills' => ['Algèbre linéaire', 'Analyse mathématique', 'Probabilités & Statistiques', 'Physique']
            ],
            [
                'nom' => 'Méthodologie & Soft Skills',
                'slug' => 'methodologie-soft-skills',
                'description' => 'Gestion du temps, prise de parole en public, rédaction de rapports techniques.',
                'icon' => 'bi-lightbulb',
                'skills' => ['Prise de parole', 'Rédaction de PFE / Rapport', 'Gestion de projet Agile', 'Git & GitHub']
            ],
            [
                'nom' => 'Design & Multimédia',
                'slug' => 'design-multimedia',
                'description' => 'UI/UX design, Figma, création de supports visuels et infographies.',
                'icon' => 'bi-palette',
                'skills' => ['Figma UI/UX', 'Photoshop', 'Canva Pro', 'Montage vidéo']
            ],
        ];

        $categoriesMap = [];
        foreach ($categoriesData as $catItem) {
            $cat = Category::create([
                'nom' => $catItem['nom'],
                'slug' => $catItem['slug'],
                'description' => $catItem['description'],
                'icon' => $catItem['icon'],
            ]);
            $categoriesMap[$catItem['slug']] = $cat;

            foreach ($catItem['skills'] as $skillName) {
                Skill::create([
                    'category_id' => $cat->id,
                    'nom' => $skillName,
                ]);
            }
        }

        // 2. Administrateur
        $admin = User::create([
            'nom' => 'Admin',
            'prenom' => 'Super',
            'email' => 'admin@skillswap.edu',
            'password' => Hash::make('password'),
            'filiere' => 'Administration ESTB',
            'niveau_etudes' => 'Personnel',
            'solde_disponible' => 10,
            'solde_bloque' => 0,
            'role' => 'admin',
            'is_active' => true,
        ]);

        // 3. Étudiants
        $studentsData = [
            [
                'nom' => 'Alami',
                'prenom' => 'Sara',
                'email' => 'sara@skillswap.edu',
                'filiere' => 'DUT Génie Informatique',
                'niveau_etudes' => '2ème année (S3)',
                'propose' => ['Python', 'PHP / Laravel'],
                'cherche' => ['Anglais technique', 'Présentation orale'],
            ],
            [
                'nom' => 'Benali',
                'prenom' => 'Amine',
                'email' => 'amine@skillswap.edu',
                'filiere' => 'DUT Génie Informatique',
                'niveau_etudes' => '2ème année (S3)',
                'propose' => ['SQL & BDD', 'Algorithmique & C'],
                'cherche' => ['Python', 'PHP / Laravel'],
            ],
            [
                'nom' => 'Chraibi',
                'prenom' => 'Yassine',
                'email' => 'yassine@skillswap.edu',
                'filiere' => 'DUT Techniques de Management',
                'niveau_etudes' => '1ère année (S1)',
                'propose' => ['Anglais technique', 'Présentation orale'],
                'cherche' => ['Figma UI/UX', 'SQL & BDD'],
            ],
        ];

        $createdStudents = [];
        foreach ($studentsData as $st) {
            $student = User::create([
                'nom' => $st['nom'],
                'prenom' => $st['prenom'],
                'email' => $st['email'],
                'password' => Hash::make('password'),
                'filiere' => $st['filiere'],
                'niveau_etudes' => $st['niveau_etudes'],
                'solde_disponible' => 2, // RG01
                'solde_bloque' => 0,
                'role' => 'etudiant',
                'is_active' => true,
            ]);

            // Trace de bienvenue RG01 & RG16
            CreditTransaction::create([
                'user_id' => $student->id,
                'montant' => 2,
                'type' => 'bienvenue',
                'description' => 'Attribution des 2 crédits gratuits de bienvenue',
            ]);

            foreach ($st['propose'] as $p) {
                UserSkill::create([
                    'user_id' => $student->id,
                    'competence' => $p,
                    'type' => 'proposee',
                ]);
            }

            foreach ($st['cherche'] as $c) {
                UserSkill::create([
                    'user_id' => $student->id,
                    'competence' => $c,
                    'type' => 'recherchee',
                ]);
            }

            $createdStudents[$st['email']] = $student;
        }

        // 4. Offres & Créneaux types
        $sara = $createdStudents['sara@skillswap.edu'];
        $amine = $createdStudents['amine@skillswap.edu'];
        $yassine = $createdStudents['yassine@skillswap.edu'];

        // Offre Sara : Initiation Python
        $offre1 = Offer::create([
            'user_id' => $sara->id,
            'category_id' => $categoriesMap['informatique-programmation']->id,
            'titre' => 'Initiation pratique à Python pour débutants',
            'competence' => 'Python',
            'description' => 'Séance interactive pour comprendre les bases de Python : types de variables, conditions, boucles et fonctions pratiques avec exercices corrigés.',
            'niveau' => 'debutant',
            'duree' => 1, // 1h = 1 crédit
            'mode' => 'presentiel',
            'status' => 'publiee',
            'is_masked' => false,
        ]);

        Slot::create([
            'offer_id' => $offre1->id,
            'date_debut' => Carbon::now()->addDays(2)->setTime(14, 0),
            'date_fin' => Carbon::now()->addDays(2)->setTime(15, 0),
            'lieu_ou_lien' => 'Salle B12 - Département Informatique',
            'status' => 'disponible',
        ]);

        Slot::create([
            'offer_id' => $offre1->id,
            'date_debut' => Carbon::now()->addDays(4)->setTime(10, 0),
            'date_fin' => Carbon::now()->addDays(4)->setTime(11, 0),
            'lieu_ou_lien' => 'Bibliothèque Universitaire - Salle d\'étude 3',
            'status' => 'disponible',
        ]);

        // Offre Sara 2 : Modélisation Laravel (2 heures)
        $offre2 = Offer::create([
            'user_id' => $sara->id,
            'category_id' => $categoriesMap['informatique-programmation']->id,
            'titre' => 'Atelier Laravel MVC : migrations, modèles et contrôleurs',
            'competence' => 'PHP / Laravel',
            'description' => 'Créer une application web complète avec Laravel en 2 heures. Comprendre le routage, Blade, Eloquent et les relations de tables.',
            'niveau' => 'intermediaire',
            'duree' => 2, // 2h = 2 crédits
            'mode' => 'distanciel',
            'status' => 'publiee',
            'is_masked' => false,
        ]);

        Slot::create([
            'offer_id' => $offre2->id,
            'date_debut' => Carbon::now()->addDays(3)->setTime(16, 0),
            'date_fin' => Carbon::now()->addDays(3)->setTime(18, 0),
            'lieu_ou_lien' => 'https://meet.google.com/abc-skillswap-sara',
            'status' => 'disponible',
        ]);

        // Offre Amine : SQL & Modélisation relationnelle
        $offre3 = Offer::create([
            'user_id' => $amine->id,
            'category_id' => $categoriesMap['informatique-programmation']->id,
            'titre' => 'Concevoir ses requêtes SQL et jointures sans erreur',
            'competence' => 'SQL & BDD',
            'description' => 'Comprendre INNER JOIN, LEFT JOIN, GROUP BY et les clés étrangères pour réussir vos examens et projets de BDD.',
            'niveau' => 'debutant',
            'duree' => 1,
            'mode' => 'presentiel',
            'status' => 'publiee',
            'is_masked' => false,
        ]);

        Slot::create([
            'offer_id' => $offre3->id,
            'date_debut' => Carbon::now()->addDays(2)->setTime(16, 0),
            'date_fin' => Carbon::now()->addDays(2)->setTime(17, 0),
            'lieu_ou_lien' => 'Laboratoire Informatique L02',
            'status' => 'disponible',
        ]);

        // Offre Yassine : Anglais Oral & Pitch
        $offre4 = Offer::create([
            'user_id' => $yassine->id,
            'category_id' => $categoriesMap['langues-communication']->id,
            'titre' => 'Pratique d\'anglais oral et pitch de projet en anglais',
            'competence' => 'Anglais technique',
            'description' => 'Conversation fluide en anglais pour vous préparer à présenter un projet technique ou réussir un entretien de stage.',
            'niveau' => 'intermediaire',
            'duree' => 1,
            'mode' => 'distanciel',
            'status' => 'publiee',
            'is_masked' => false,
        ]);

        Slot::create([
            'offer_id' => $offre4->id,
            'date_debut' => Carbon::now()->addDays(1)->setTime(18, 0),
            'date_fin' => Carbon::now()->addDays(1)->setTime(19, 0),
            'lieu_ou_lien' => 'https://meet.google.com/eng-skillswap-yassine',
            'status' => 'disponible',
        ]);
    }
}
