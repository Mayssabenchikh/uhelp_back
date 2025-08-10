<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1) On change la colonne en VARCHAR(255)
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('statut', 255)->default('ouvert')->change();
        });

        // 2) Si tu veux mapper les anciennes valeurs ENUM vers de nouvelles valeurs texte (optionnel)
        DB::table('tickets')->update([
            'statut' => DB::raw("
                CASE
                    WHEN statut = 'ouvert' THEN 'ouvert'
                    WHEN statut = 'en_cours' THEN 'en_cours'
                    WHEN statut = 'résolu' THEN 'résolu'
                    WHEN statut = 'fermé' THEN 'fermé'
                    ELSE statut
                END
            ")
        ]);
    }

    public function down()
    {
        // Retour en ENUM si on rollback
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('statut', ['ouvert', 'en_cours', 'résolu', 'fermé'])->default('ouvert')->change();
        });
    }
};

