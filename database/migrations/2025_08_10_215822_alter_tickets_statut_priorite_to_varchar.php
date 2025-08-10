<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Changer ENUM -> VARCHAR
            $table->string('statut', 50)->default('open')->change();
            $table->string('priorite', 50)->nullable()->default('medium')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('statut', ['ouvert','en_cours','résolu','fermé'])->default('ouvert')->change();
            $table->enum('priorite', ['faible','moyenne','élevée','urgente'])->default('moyenne')->change();
        });
    }
};
