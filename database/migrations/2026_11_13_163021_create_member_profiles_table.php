<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('member_number', 30)->unique();
            $table->date('joined_at');
            $table->date('expired_at')->nullable();
            $table->string('status', 20)->default('active')->index(); // active | suspended | expired
            $table->string('card_token', 64)->unique(); // token verifikasi QR
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
    }
};
