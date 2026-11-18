<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->restrictOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cible_id')->constrained('users')->restrictOnDelete();
            $table->tinyInteger('note'); // 1 à 5
            $table->text('commentaire')->nullable();
            $table->timestamps();

            // RG15 : Un participant ne peut publier qu'une seule évaluation par séance
            $table->unique(['reservation_id', 'auteur_id'], 'uk_evaluation_unique_par_auteur');
        });

        // RG15 : Note comprise entre 1 et 5
        DB::statement('ALTER TABLE evaluations ADD CONSTRAINT chk_evaluation_note CHECK (note BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};

