# SkillSwap Campus

Application d’échange de compétences entre étudiants : une heure correspond à un crédit. Chaque étudiant reçoit deux crédits à l’inscription.

## Environnement

- PHP 8.2 ou supérieur, Composer et extensions PDO MySQL, SQLite (tests unitaires), mbstring, XML et cURL.
- MySQL 8.0.16 ou supérieur avec InnoDB. Les contraintes et triggers fournis nécessitent MySQL ; SQLite n’est pas une base de production prise en charge.
- Node.js 24 LTS (24.15 ou supérieur), npm et Chrome pour les tests UI.
- Laravel 12 / Sanctum, React 19 / TypeScript, Vite et Tailwind CSS.

## Installation locale

Créez une base MySQL vide nommée `skillswap` et configurez un compte autorisé à créer ses tables, contraintes et triggers.

```sh
composer install
cp .env.example .env
# Adapter DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME et DB_PASSWORD dans .env.
php artisan key:generate
php artisan migrate
php artisan db:seed
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Ouvrez http://127.0.0.1:8000. Les données persistent dans MySQL après redémarrage. Pour travailler sur l’interface, lancez `npm run dev` dans un deuxième terminal.

Le seeder est réexécutable sans doubler les comptes ni les crédits. Comptes locaux de démonstration (mot de passe `password`) :

| Rôle | Adresse |
| --- | --- |
| Administrateur | admin@skillswap.edu |
| Étudiante | sara@skillswap.edu |
| Étudiant | amine@skillswap.edu |
| Étudiant | yassine@skillswap.edu |

Ces comptes sont destinés à une installation locale de démonstration.

## Parcours

- Profil modifiable, compétences proposées/recherchées, soldes et historique des crédits.
- Suggestions selon les compétences recherchées et créneaux futurs disponibles.
- Catalogue filtré par texte, catégorie, niveau, date, mode et durée.
- Offres, publication, archivage et gestion des créneaux ; coordonnées privées réservées aux personnes autorisées.
- Demandes reçues/envoyées, acceptation, refus motivé, retrait et annulation avant début.
- Double confirmation après fin, transfert unique, évaluations et notifications persistantes.
- Litiges avec témoignages des participants et décision motivée de l’administrateur.
- Administration des comptes, catégories, modération, statistiques et règlement des séances de comptes désactivés.

## Vérifications

```sh
php artisan test
npm run typecheck
npm test
npm run build
```

La suite PHP historique utilise majoritairement des schémas SQLite isolés. Les anciens tests Blade obsolètes restent explicitement marqués comme tels ; ne pas présenter leur nombre comme preuve de conformité complète.

Pour vérifier également les véritables migrations et contraintes, créez une base MySQL dédiée `skillswap_integration_test`, configurez les identifiants de connexion locaux, puis :

```sh
TEST_MYSQL_DATABASE=skillswap_integration_test php artisan test tests/Integration
```

Cette commande réinitialise uniquement la base de test indiquée, dont le nom doit commencer par `skillswap_` et finir par `test`. Elle ne doit jamais cibler une base contenant des données à conserver.

Sur une installation locale migrée, alimentée et compilée, arrêtez le serveur manuel avant de lancer les tests UI : Playwright démarre un serveur local avec quatre workers pour les sessions simultanées (macOS/Linux). Vérifiez les parcours dans Chrome :

```sh
npm run test:ui
npm run test:e2e
```

Les tests UI créent leurs propres étudiants. Leur préparation temporelle est limitée aux comptes `teacher.ui.*@campus.test` et aux environnements local/testing. Les actions utilisateur sont effectuées dans le navigateur. Les traces et captures d’échec se trouvent dans `test-results/`, le rapport dans `playwright-report/`.

## Livrables universitaires

Le cahier des charges demande également un rapport PDF, les diagrammes UML et leurs sources modifiables. Ces livrables doivent être préparés et remis avec le code ; les tests de l’application ne les remplacent pas.
