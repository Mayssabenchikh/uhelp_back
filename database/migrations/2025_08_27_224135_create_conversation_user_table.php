<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversationUserTable extends Migration
{
    public function up()
    {
        Schema::create('conversation_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['conversation_id','user_id']);
            $table->index('conversation_id');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('conversation_user');
    }
}
