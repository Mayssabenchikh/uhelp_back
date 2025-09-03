<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocationToUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ajoute la colonne location après phone_number, nullable pour compatibilité
            if (!Schema::hasColumn('users', 'location')) {
                $table->string('location')->nullable()->after('phone_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'location')) {
                $table->dropColumn('location');
            }
        });
    }
}
