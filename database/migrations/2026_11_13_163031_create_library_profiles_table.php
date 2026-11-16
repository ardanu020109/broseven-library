<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('head_name')->nullable();
            $table->text('operating_hours')->nullable();
            $table->text('borrowing_rules')->nullable();
            $table->unsignedSmallInteger('max_books_per_loan')->default(3);
            $table->unsignedSmallInteger('loan_duration_days')->default(7);
            $table->decimal('fine_per_day', 12, 2)->default(2000);
            $table->json('social_media')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_profiles');
    }
};
