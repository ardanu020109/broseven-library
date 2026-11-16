<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('borrowing_item_id')->constrained('borrowing_items')->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);

            // Snapshot saat pengembalian, agar perubahan tarif tidak mengubah histori.
            $table->date('due_date');
            $table->unsignedInteger('days_late')->default(0);
            $table->decimal('fine_per_day', 12, 2)->default(0);
            $table->decimal('fine_amount', 12, 2)->default(0);

            $table->timestamps();

            $table->index('borrowing_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
