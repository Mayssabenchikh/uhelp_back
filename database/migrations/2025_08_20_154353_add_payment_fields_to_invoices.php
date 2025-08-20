<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable()->unique()->after('id');
            $table->string('provider_payment_id')->nullable()->after('payment_id');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn(['payment_id','provider_payment_id']);
        });
    }
};
