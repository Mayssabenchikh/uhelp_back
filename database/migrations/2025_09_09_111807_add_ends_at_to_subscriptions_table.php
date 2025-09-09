<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEndsAtToSubscriptionsTable extends Migration
{
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // place la colonne après current_period_ends_at si elle existe, sinon à la fin
            if (Schema::hasColumn('subscriptions', 'current_period_ends_at')) {
                $table->timestamp('ends_at')->nullable()->after('current_period_ends_at');
            } else {
                $table->timestamp('ends_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'ends_at')) {
                $table->dropColumn('ends_at');
            }
        });
    }
}
