<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creneau_id')->constrained('creneaux')->restrictOnDelete();
            $table->foreignId('beneficiaire_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('date_demande')->useCurrent();
            $table->enum('statut', [
                'en_attente', 
                'refusee', 
                'acceptee', 
                'annulee', 
                'realisee', 
                'en_litige'
            ])->default('en_attente'); // RG09
            
            $table->tinyInteger('cout_credits'); // RG02
            $table->text('motif_refus')->nullable(); // RG07, RG09
            
            // RG11 : Confirmation bilatérale post-séance
            $table->boolean('confirmation_intervenant')->default(false);
            $table->timestamp('date_confirmation_intervenant')->nullable();
            $table->boolean('confirmation_beneficiaire')->default(false);
            $table->timestamp('date_confirmation_beneficiaire')->nullable();
            
            $table->timestamps();
        });

        // 1. Correction RG03 : Unicité active seulement. Si refusée ou annulée -> valeur NULL, réattribution permise
        DB::statement("
            ALTER TABLE reservations 
            ADD COLUMN actif_beneficiaire_id BIGINT UNSIGNED 
            GENERATED ALWAYS AS (IF(statut IN ('en_attente', 'acceptee'), beneficiaire_id, NULL)) STORED
        ");
        DB::statement("
            ALTER TABLE reservations 
            ADD CONSTRAINT uk_reservation_active_creneau_beneficiaire 
            UNIQUE (creneau_id, actif_beneficiaire_id)
        ");

        // 2. Correction RG07 : Un seul créneau attribué au niveau moteur BDD (anti-concurrence)
        DB::statement("
            ALTER TABLE reservations 
            ADD COLUMN creneau_id_attribue BIGINT UNSIGNED 
            GENERATED ALWAYS AS (IF(statut IN ('acceptee', 'realisee', 'en_litige'), creneau_id, NULL)) STORED
        ");
        DB::statement("
            ALTER TABLE reservations 
            ADD CONSTRAINT uk_creneau_unique_attribution 
            UNIQUE (creneau_id_attribue)
        ");

        DB::statement('ALTER TABLE reservations ADD CONSTRAINT chk_reservation_cout CHECK (cout_credits IN (1, 2))');
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};

