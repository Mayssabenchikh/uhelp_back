<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('conversations', function (Blueprint $table) {
            // ajout d'un champ type; si tu veux nullable, remplace default par nullable
            $table->enum('type', ['private', 'group'])->default('private')->after('title');
        });
    }

    public function down()
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
