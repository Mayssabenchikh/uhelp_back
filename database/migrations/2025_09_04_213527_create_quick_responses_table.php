<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quick_responses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('language', 10)->default('en'); // en, fr, ...
            $table->string('category')->nullable(); // ex: faq, support, greeting
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('user_id')->nullable(); // si besoin
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_responses');
    }
};
