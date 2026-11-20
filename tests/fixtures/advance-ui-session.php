<?php

use App\Models\Creneau;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment(['local', 'testing'])) {
    throw new RuntimeException('Local UI fixtures only.');
}
$email = $argv[1] ?? '';
if (! preg_match('/^teacher\.ui\.\d+@campus\.test$/', $email)) {
    throw new RuntimeException('UI fixture account required.');
}
Creneau::whereHas('offre.intervenant', fn ($q) => $q->where('email', $email))->update(['date' => now()->subDay()->toDateString()]);
echo "UI session time boundary prepared.\n";
