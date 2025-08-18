<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'subscription_id')) {
                // place after client_id (use any existing column or omit ->after(...) to append)
                $table->foreignId('subscription_id')
                      ->nullable()             // modifier must come before constrained()
                      ->after('client_id')     // <-- change this to an existing column OR remove this line
                      ->constrained('subscriptions')
                      ->nullOnDelete();        // set NULL on delete
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'subscription_id')) {
                // Laravel helper to drop constrained foreign id (works on recent Laravel versions)
                $table->dropConstrainedForeignId('subscription_id');
            }
        });
    }
};
