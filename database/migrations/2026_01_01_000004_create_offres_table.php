<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('categorie_id')->constrained('categories')->restrictOnDelete();
            $table->string('titre', 200);
            $table->string('competence', 150);
            $table->text('description');
            $table->enum('niveau', ['debutant', 'intermediaire', 'avance']);
            $table->tinyInteger('duree'); // RG02 : 1 ou 2 heures
            $table->enum('mode', ['presentiel', 'distanciel'])->default('presentiel');
            $table->enum('statut', ['brouillon', 'publiee', 'archivee', 'masquee'])->default('brouillon'); // RG04, RG17
            $table->timestamps();
        });

        // RG02 : Durée strictement 1h ou 2h
        DB::statement('ALTER TABLE offres ADD CONSTRAINT chk_offre_duree CHECK (duree IN (1, 2))');
    }

    public function down(): void
    {
        Schema::dropIfExists('offres');
    }
};

