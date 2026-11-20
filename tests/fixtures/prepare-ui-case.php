<?php

use App\Models\Categorie;
use App\Models\Creneau;
use App\Models\User;
use App\Services\AuthService;
use App\Services\CreneauService;
use App\Services\OffreService;
use App\Services\ReservationService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment(['local', 'testing'])) {
    throw new RuntimeException('Local UI fixtures only.');
}
$case = $argv[1] ?? '';
if (! in_array($case, ['empty', 'pending', 'accepted', 'past', 'moderation'])) {
    throw new RuntimeException('Unsupported UI fixture.');
}
$tag = bin2hex(random_bytes(5));
$users = [];
foreach (['teacher', 'learner', 'other'] as $role) {
    $email = "$role.ui.$tag@campus.test";
    $result = app(AuthService::class)->register(['nom' => 'Test', 'prenom' => ucfirst($role).$tag, 'email' => $email, 'password' => 'Password123!']);
    $users[$role] = User::findOrFail($result['user']['id']);
}
$title = "UI $case $tag";
$offer = app(OffreService::class)->creerOffre($users['teacher'], ['categorie_id' => Categorie::firstOrFail()->id, 'titre' => $title, 'competence' => 'Python', 'description' => 'Une séance de démonstration pour les tests UI.', 'duree' => 1, 'mode' => 'presentiel', 'niveau' => 'debutant']);
app(OffreService::class)->publierOffre($users['teacher'], $offer->id);
$slot = app(CreneauService::class)->ajouterCreneau($users['teacher'], $offer->id, ['date' => now()->addDays(6)->toDateString(), 'heure_debut' => '16:00', 'heure_fin' => '17:00', 'lieu_public' => 'Campus UI', 'lien_ou_lieu_prive' => 'Salle privée '.$tag]);
$service = app(ReservationService::class);
$r = $case === 'empty' ? null : $service->demanderReservation($users['learner'], $slot->id);
if ($case === 'pending') {
    $service->demanderReservation($users['other'], $slot->id);
}
if (in_array($case, ['accepted', 'past', 'moderation'])) {
    $service->accepterReservation($users['teacher'], $r->id);
}
if ($case === 'past') {
    Creneau::whereKey($slot->id)->update(['date' => now()->subDay()->toDateString()]);
}
if ($case === 'moderation') {
    $slot2 = app(CreneauService::class)->ajouterCreneau($users['teacher'], $offer->id, ['date' => now()->addDays(7)->toDateString(), 'heure_debut' => '16:00', 'heure_fin' => '17:00', 'lieu_public' => 'Campus UI', 'lien_ou_lieu_prive' => 'Autre salle privée']);
    $service->demanderReservation($users['other'], $slot2->id);
}
echo json_encode(['title' => $title, 'offer' => $offer->id, 'reservation' => $r?->id, 'teacher' => $users['teacher']->email, 'learner' => $users['learner']->email, 'other' => $users['other']->email, 'learnerName' => $users['learner']->prenom]);
