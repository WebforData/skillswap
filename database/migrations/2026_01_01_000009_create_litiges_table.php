<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('litiges', function (Blueprint $table) {
            $table->id();
            // RG14 : Unicité absolue du dossier de litige par réservation (aucun second arbitrage)
            $table->foreignId('reservation_id')->unique()->constrained('reservations')->restrictOnDelete();
            $table->foreignId('declarant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('description');
            $table->enum('statut', ['ouvert', 'resolu'])->default('ouvert'); // RG12, RG13
            $table->enum('decision', [
                'transfert_intervenant', 
                'restitution_beneficiaire'
            ])->nullable(); // RG13 : Arbitrage intégral uniquement
            $table->text('motif_decision')->nullable(); // Décision motivée
            $table->timestamp('date_resolution')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('litiges');
    }
};

