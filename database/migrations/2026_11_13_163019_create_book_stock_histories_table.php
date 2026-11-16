<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_stock_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20); // initial | adjustment | borrow | return
            $table->integer('quantity_change');
            $table->unsignedInteger('total_after');
            $table->unsignedInteger('available_after');
            $table->unsignedInteger('borrowed_after');
            $table->nullableMorphs('reference');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['book_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_stock_histories');
    }
};
