<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id'); // lien avec User
            $table->tinyInteger('day_of_week'); // 0 = dimanche, 6 = samedi
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->foreign('agent_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade'); // supprime les horaires si agent supprimé
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
