<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fine_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fine_id')->constrained('fines')->restrictOnDelete();
            $table->string('type', 20)->default('payment'); // payment | waiver
            $table->decimal('amount', 12, 2);
            $table->string('method', 20)->nullable(); // cash | transfer | other
            $table->dateTime('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['fine_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fine_payments');
    }
};
