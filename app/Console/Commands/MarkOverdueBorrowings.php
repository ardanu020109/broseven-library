<?php

namespace App\Console\Commands;

use App\Services\BorrowingService;
use Illuminate\Console\Command;

class MarkOverdueBorrowings extends Command
{
    protected $signature = 'library:mark-overdue';

    protected $description = 'Menandai peminjaman yang sudah lewat jatuh tempo sebagai terlambat';

    public function handle(BorrowingService $borrowings): int
    {
        $count = $borrowings->markOverdue();

        $this->info("{$count} peminjaman ditandai terlambat.");

        return self::SUCCESS;
    }
}
