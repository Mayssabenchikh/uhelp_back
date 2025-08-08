<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
       Schema::create('tickets', function (Blueprint $table) {
    $table->id();
    $table->string('titre', 255);
    $table->text('description')->nullable();
    
    $table->enum('statut', ['ouvert', 'en_cours', 'résolu', 'fermé'])->default('ouvert');
    $table->enum('priorite', ['faible', 'moyenne', 'élevée', 'urgente'])->default('moyenne');
    
    // Relations avec vérification de rôle
    $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('agentassigne_id')->nullable()->constrained('users')->onDelete('set null');
    
    $table->timestamp('closed_at')->nullable();
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};