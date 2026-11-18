<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portefeuilles', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete : interdit la suppression d'un portefeuille ayant un historique
            $table->foreignId('etudiant_id')->unique()->constrained('users')->restrictOnDelete();
            $table->integer('solde_disponible')->default(0); // Démarre à 0, crédité via transaction de bienvenue
            $table->integer('credits_bloques')->default(0);
            $table->timestamps();
        });

        // RG16 : Les soldes ne peuvent jamais être négatifs
        DB::statement('ALTER TABLE portefeuilles ADD CONSTRAINT chk_solde_disponible CHECK (solde_disponible >= 0)');
        DB::statement('ALTER TABLE portefeuilles ADD CONSTRAINT chk_credits_bloques CHECK (credits_bloques >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('portefeuilles');
    }
};

