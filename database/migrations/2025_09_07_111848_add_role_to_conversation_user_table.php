<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRoleToConversationUserTable extends Migration
{
    public function up()
    {
        Schema::table('conversation_user', function (Blueprint $table) {
            // ajouter la colonne role si elle n'existe pas
            if (! Schema::hasColumn('conversation_user', 'role')) {
                $table->string('role')->nullable()->default('member')->after('user_id');
            }
        });
    }

    public function down()
    {
        Schema::table('conversation_user', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_user', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
}
