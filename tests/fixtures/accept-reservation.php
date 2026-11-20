<?php

use App\Exceptions\ConflitHoraireException;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! preg_match('/^skillswap_[a-z0-9_]*test$/', config('database.connections.mysql.database'))) {
    throw new RuntimeException('Isolated test database required.');
}
try {
    app(ReservationService::class)->accepterReservation(User::findOrFail($argv[1]), (int) $argv[2]);
    echo 'accepted';
} catch (ConflitHoraireException $e) {
    echo 'schedule_conflict';
}
