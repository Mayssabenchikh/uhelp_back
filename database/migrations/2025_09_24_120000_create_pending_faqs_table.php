<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pending_faqs', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('language', 10)->default('en');
            $table->string('category')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('raw_model_output')->nullable();
            $table->timestamps();
        });

        Schema::table('pending_faqs', function (Blueprint $table) {
            $table->index('status');
            $table->index('language');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_faqs');
    }
};
