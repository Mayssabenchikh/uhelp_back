<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->text('answer');
            $table->string('language', 10)->default('en');
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        // Optional: add a simple index to help LIKE searches
        Schema::table('faqs', function (Blueprint $table) {
            $table->index('language');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
