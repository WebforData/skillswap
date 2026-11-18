<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creneaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offre_id')->constrained('offres')->cascadeOnDelete();
            $table->date('date');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('lieu_public', 150); // Visible lors des recherches (ex: "Campus EST" ou "En ligne")
            $table->text('lien_ou_lieu_prive');  // RG19 : Confidentiel, accessible uniquement aux participants
            $table->timestamps();
        });

        // Validation de cohérence des heures
        DB::statement('ALTER TABLE creneaux ADD CONSTRAINT chk_creneau_chronologie CHECK (heure_fin > heure_debut)');
        // RG02 : Durée autorisée uniquement de 60 ou 120 minutes
        DB::statement('ALTER TABLE creneaux ADD CONSTRAINT chk_creneau_duree_valide CHECK (TIMESTAMPDIFF(MINUTE, heure_debut, heure_fin) IN (60, 120))');
    }

    public function down(): void
    {
        Schema::dropIfExists('creneaux');
    }
};

