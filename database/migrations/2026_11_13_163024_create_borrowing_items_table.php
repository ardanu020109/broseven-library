<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrowing_id')->constrained('borrowings')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('returned_quantity')->default(0);
            $table->string('return_status', 20)->default('not_returned'); // not_returned | partially_returned | returned
            $table->timestamps();

            $table->unique(['borrowing_id', 'book_id']);
            $table->index('book_id');
        });

        // Pencegahan pengembalian ganda di level database.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(
                'ALTER TABLE borrowing_items ADD CONSTRAINT chk_borrowing_items_returned CHECK (returned_quantity <= quantity)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowing_items');
    }
};
