# SkillSwap Campus — Plateforme Universitaire d'Échange de Compétences

> **Module :** GL & Modélisation UML (M234)  
> **Filière :** DUT Génie Informatique (S3) — Année Universitaire 2026/2027  
> **Établissement :** École Supérieure de Technologie (EST) Beni Mellal  

---

## 1. Présentation du Projet

**SkillSwap Campus** est une application web universitaire de gestion des échanges de compétences entre étudiants, fondée sur une **banque de temps** :
- **1 heure animée = 1 crédit gagné = 1 heure de formation reçue.**
- Chaque nouvel étudiant reçoit **2 crédits gratuits de bienvenue** à l'inscription (RG01).
- **Aucun paiement en argent** n'intervient dans le système.
- Un même étudiant peut être formateur (intervenant) pour une séance et bénéficiaire pour une autre.

---

## 2. Technologies Utilisées

- **Backend :** PHP 8.2+ & Laravel (Architecture MVC + Services Métiers)
- **Base de Données :** MySQL (Moteur InnoDB avec transactions SQL atomiques et verrouillage pessimiste)
- **Frontend :** HTML5, CSS3, Blade, Bootstrap 5.3 & Bootstrap Icons
- **Visualisation de Données :** Chart.js pour le Dashboard Administrateur
- **Environnement local :** Windows 11 avec XAMPP & Composer

---

## 3. Matrice des 19 Règles de Gestion (RG01 à RG19)

| Règle | Description | Implémentation dans le code |
| :---: | :--- | :--- |
| **RG01** | Unicité de l'email + 2 crédits attribués une seule fois | `RegisterRequest`, `AuthController::register`, `CreditWalletService::creditWelcomeBonus` |
| **RG02** | Séance individuelle (1h = 1 crédit, 2h = 2 crédits) | `create_offers_table`, `OfferRequest`, `SlotController::store` |
| **RG03** | Interdiction d'auto-réservation et de doublons de demandes | `ReservationService::createPendingReservation`, index unique BDD `(slot_id, beneficiary_id)` |
| **RG04** | Offre publiée, intervenant actif et créneau futur disponible | `Offer::scopeActivePublic`, `ReservationService::createPendingReservation` |
| **RG05** | Vérification solde disponible à la demande ET à l'acceptation | `ReservationService::createPendingReservation`, `CreditWalletService::lockCreditsForReservation` |
| **RG06** | Blocage des crédits chez le bénéficiaire lors de l'acceptation | `CreditWalletService::lockCreditsForReservation` via `lockForUpdate` |
| **RG07** | 1 réservation par créneau, refus auto des autres avec motif | `ReservationService::acceptReservation` (mise à jour auto en `rejected`) |
| **RG08** | Interdiction des chevauchements d'horaires pour un étudiant | `ReservationService::checkScheduleConflict` (vérification bilatérale) |
| **RG09** | Cycle d'états (pending, rejected, accepted, cancelled, completed, dispute) | `Reservation::status`, `ReservationController`, boutons d'action dédiés |
| **RG10** | Annulation avant début = crédits libérés et créneau réouvert | `ReservationService::cancelAcceptedReservation`, `CreditWalletService::releaseCreditsAfterCancellation` |
| **RG11** | Double confirmation post-séance requise pour transférer les crédits | `ReservationService::confirmReservation`, `CreditWalletService::transferCreditsAfterCompletion` |
| **RG12** | Crédits maintenus bloqués si confirmation manquante, signalement possible | `ReservationService::confirmReservation`, `DisputeController::create` |
| **RG13** | Litige = crédits bloqués, décision binaire admin (sans partage partiel) | `CreditWalletService::resolveDispute`, `DisputeResolutionController::resolve` |
| **RG14** | Décision administrative définitive, aucun second règlement possible | Contrôle `isResolved()` dans `DisputeResolutionController::resolve` |
| **RG15** | 1 seule évaluation (1 à 5 étoiles) par participant après séance achevée | `EvaluationController::store`, index unique BDD `(reservation_id, evaluator_id)` |
| **RG16** | Soldes toujours $\ge 0$, historique immuable de chaque mouvement | Colonnes `unsignedInteger` BDD + `credit_transactions` |
| **RG17** | Archiver/masquer offre empêche nouvelles demandes et annule demandes en attente | `OfferController::archive`, `Admin\OfferModerationController::toggleMask` |
| **RG18** | Compte désactivé ne peut plus se connecter, demandes en attente annulées | `CheckActiveUser` Middleware, `Admin\UserController::toggleStatus` |
| **RG19** | Lieu/lien privé visible uniquement par participants confirmés et admin | `Reservation::canViewPrivateLocation`, masquage conditionnel Blade |

---

## 4. Comptes de Démonstration (Prêts pour la Soutenance)

Le seeder a créé les comptes suivants (Mot de passe pour tous : **`password`**) :

| Rôle | Nom | Email | Particularité |
| :--- | :--- | :--- | :--- |
| **Administrateur** | Super Admin | `admin@skillswap.edu` | Accès au Dashboard Chart.js, modération et arbitrage des litiges |
| **Étudiant (Formateur)** | Sara Alami | `sara@skillswap.edu` | Propose des séances de Python (1h) et Laravel (2h) |
| **Étudiant (Bénéficiaire)** | Amine Benali | `amine@skillswap.edu` | Propose SQL & BDD, cherche Python, possède ses 2 crédits de bienvenue |
| **Étudiant** | Yassine Chraibi | `yassine@skillswap.edu` | Propose Anglais technique & communication |

> **Astuce soutenance :** Sur la page de connexion (`/connexion`), des boutons de raccourcis permettent de pré-remplir les identifiants de Sara, Amine ou de l'Admin en 1 clic !

---

## 5. Démarrage de l'Application sous Windows 11

1. **Démarrer MySQL sous XAMPP :**
   Vérifiez que le service MySQL est vert dans le panneau de contrôle XAMPP.

2. **Lancer le serveur de développement Laravel :**
   ```bash
   cd C:\Users\ourou\.gemini\antigravity\scratch\skillswap-campus
   php artisan serve
   ```

3. **Accéder à l'application dans votre navigateur :**
   Ouvrez [http://localhost:8000](http://localhost:8000).

---

## 6. Lancement des Tests Automatisés

Pour exécuter la suite de tests validant les règles de gestion :
```bash
php artisan test --filter=SkillSwapRulesTest
```
Résultat : **`OK (9 tests, 34 assertions)`** — 100% de réussite.
