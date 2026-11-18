<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Portefeuille;
use App\Models\Categorie;
use App\Models\Offre;
use App\Models\Creneau;
use App\Models\Reservation;
use App\Models\MouvementCredit;
use App\Models\Evaluation;
use App\Models\Litige;

echo "=== TEST D'INSTANCIATION ET DE CONFIGURATION DES RELATIONS ELOQUENT ===\n";

// 1. User
$user = new User();
assert($user->portefeuille() instanceof \Illuminate\Database\Eloquent\Relations\HasOne);
assert($user->offres() instanceof \Illuminate\Database\Eloquent\Relations\HasMany);
assert($user->reservationsBeneficiaire() instanceof \Illuminate\Database\Eloquent\Relations\HasMany);
assert($user->evaluationsRedigees() instanceof \Illuminate\Database\Eloquent\Relations\HasMany);
assert($user->evaluationsRecues() instanceof \Illuminate\Database\Eloquent\Relations\HasMany);
assert($user->litigesDeclares() instanceof \Illuminate\Database\Eloquent\Relations\HasMany);
assert($user->litigesTraites() instanceof \Illuminate\Database\Eloquent\Relations\HasMany);
echo "[OK] User: portefeuille, offres, reservationsBeneficiaire, evaluationsRedigees, evaluationsRecues, litigesDeclares, litigesTraites\n";

// 2. Portefeuille
$portefeuille = new Portefeuille();
assert($portefeuille->etudiant() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
assert($portefeuille->mouvementsCredits() instanceof \Illuminate\Database\Eloquent\Relations\HasMany);
echo "[OK] Portefeuille: etudiant, mouvementsCredits\n";

// 3. Categorie
$categorie = new Categorie();
assert($categorie->offres() instanceof \Illuminate\Database\Eloquent\Relations\HasMany);
echo "[OK] Categorie: offres\n";

// 4. Offre
$offre = new Offre();
assert($offre->intervenant() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
assert($offre->categorie() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
assert($offre->creneaux() instanceof \Illuminate\Database\Eloquent\Relations\HasMany);
echo "[OK] Offre: intervenant, categorie, creneaux\n";

// 5. Creneau
$creneau = new Creneau();
assert($creneau->offre() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
assert($creneau->reservations() instanceof \Illuminate\Database\Eloquent\Relations\HasMany);
assert($creneau->reservationAcceptee() instanceof \Illuminate\Database\Eloquent\Relations\HasOne);
echo "[OK] Creneau: offre, reservations, reservationAcceptee\n";

// 6. Reservation
$reservation = new Reservation();
assert($reservation->creneau() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
assert($reservation->beneficiaire() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
assert($reservation->mouvementsCredits() instanceof \Illuminate\Database\Eloquent\Relations\HasMany);
assert($reservation->evaluations() instanceof \Illuminate\Database\Eloquent\Relations\HasMany);
assert($reservation->litige() instanceof \Illuminate\Database\Eloquent\Relations\HasOne);
echo "[OK] Reservation: creneau, beneficiaire, mouvementsCredits, evaluations, litige\n";

// 7. MouvementCredit
$mvt = new MouvementCredit();
assert($mvt->portefeuille() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
assert($mvt->reservation() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
echo "[OK] MouvementCredit: portefeuille, reservation\n";

// 8. Evaluation
$evaluation = new Evaluation();
assert($evaluation->reservation() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
assert($evaluation->auteur() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
assert($evaluation->cible() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
echo "[OK] Evaluation: reservation, auteur, cible\n";

// 9. Litige
$litige = new Litige();
assert($litige->reservation() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
assert($litige->declarant() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
assert($litige->administrateur() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo);
echo "[OK] Litige: reservation, declarant, administrateur\n";

echo "=== TOUTES LES RELATIONS ELOQUENT SONT 100% VALIDES ET CONFORMES ! ===\n";

