<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('email', 191)->unique(); // RG01
            $table->string('password');
            $table->enum('role', ['etudiant', 'administrateur'])->default('etudiant');
            $table->enum('statut', ['actif', 'desactive'])->default('actif'); // RG18
            
            // Attributs réservés aux profils étudiants
            $table->string('filiere', 100)->nullable();
            $table->text('competences_proposees')->nullable();
            $table->text('competences_recherchees')->nullable();
            
            $table->rememberToken();
            $table->softDeletes(); // RG18 : Préservation de l'historique utilisateur
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

