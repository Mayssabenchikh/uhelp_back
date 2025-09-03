<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCategoryToTicketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Ajoute la colonne `category` (nullable) et un index léger pour les filtres.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('priorite')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
}
