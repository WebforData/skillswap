<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Creneau;

$slotId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($slotId > 0) {
    Creneau::where('id', $slotId)->update([
        'date' => '2026-01-01',
        'heure_debut' => '08:00:00',
        'heure_fin' => '09:00:00',
    ]);
    echo "Slot {$slotId} set to past: 2026-01-01 08:00:00 - 09:00:00\n";
} else {
    echo "Usage: php scratch/set_past_creneau.php <slot_id>\n";
}
