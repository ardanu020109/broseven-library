<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('member_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('requested_at');
            $table->dateTime('approved_at')->nullable();
            $table->date('borrow_date')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index('requested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowings');
    }
};
