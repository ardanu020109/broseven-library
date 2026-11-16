<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('isbn', 20)->nullable()->unique();
            $table->string('title')->index();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('author_id')->constrained('authors')->restrictOnDelete();
            $table->foreignId('publisher_id')->constrained('publishers')->restrictOnDelete();
            $table->foreignId('shelf_id')->nullable()->constrained('shelves')->nullOnDelete();
            $table->string('shelf_number', 20)->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('language', 50)->default('Indonesia');
            $table->string('edition', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('cover')->nullable();
            $table->unsignedSmallInteger('pages')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedInteger('total_stock')->default(0);
            $table->unsignedInteger('available_stock')->default(0);
            $table->unsignedInteger('borrowed_stock')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Pengaman stok di level database: stok tidak boleh tidak sinkron.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(
                'ALTER TABLE books ADD CONSTRAINT chk_books_stock CHECK (available_stock + borrowed_stock = total_stock)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
