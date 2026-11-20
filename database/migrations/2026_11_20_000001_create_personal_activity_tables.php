<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $t->string('title');
            $t->text('message');
            $t->boolean('is_read')->default(false);
            $t->timestamps();
        });
        Schema::create('litige_statements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('litige_id')->constrained('litiges')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->text('message');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('litige_statements');
        Schema::dropIfExists('notifications');
    }
};
