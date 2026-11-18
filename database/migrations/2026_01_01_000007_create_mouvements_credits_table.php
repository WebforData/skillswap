<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mouvements_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portefeuille_id')->constrained('portefeuilles')->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->restrictOnDelete();
            $table->timestamp('date_mouvement')->useCurrent();
            $table->integer('quantite'); // Positif pour crédit, négatif pour débit
            $table->enum('type', [
                'bienvenue',            // RG01
                'blocage',              // RG06
                'deblocage_annulation', // RG10
                'transfert_gain',       // RG11
                'restitution_litige'    // RG13
            ]);
            $table->timestamps();
        });

        // RG01 : Garantit qu'un portefeuille ne reçoit qu'un seul bonus de bienvenue (MySQL 8.0.16+)
        DB::statement("
            ALTER TABLE mouvements_credits 
            ADD COLUMN type_bienvenue VARCHAR(20) 
            GENERATED ALWAYS AS (IF(type = 'bienvenue', 'bienvenue', NULL)) STORED
        ");
        
        DB::statement("
            ALTER TABLE mouvements_credits 
            ADD CONSTRAINT uk_portefeuille_bienvenue_unique 
            UNIQUE (portefeuille_id, type_bienvenue)
        ");

        // CORRECTION 1 (RG11, RG16) : Unicité composite par réservation, portefeuille ET type
        DB::statement("
            ALTER TABLE mouvements_credits 
            ADD CONSTRAINT uk_reservation_portefeuille_type_unique 
            UNIQUE (reservation_id, portefeuille_id, type)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_credits');
    }
};

