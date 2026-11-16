<?php
$member = App\Models\User::where('username','budi.santoso')->first();
$staff  = App\Models\User::where('username','adminkaryawan')->first();
$a = App\Models\Book::where('title','Laskar Pelangi')->first();
$b = App\Models\Book::where('title','Atomic Habits')->first();

$borrowing = App\Models\Borrowing::create([
    'code' => 'PJM-DEMO-7B', 'member_id' => $member->id, 'requested_at' => now()->subDays(12),
    'approved_at' => now()->subDays(12), 'borrow_date' => today()->subDays(12), 'due_date' => today()->subDays(3),
    'processed_by' => $staff->id, 'status' => 'borrowed',
]);
foreach ([$a, $b] as $book) {
    App\Models\BorrowingItem::create(['borrowing_id' => $borrowing->id, 'book_id' => $book->id, 'quantity' => 1]);
    DB::table('books')->where('id', $book->id)->update([
        'available_stock' => DB::raw('available_stock - 1'), 'borrowed_stock' => DB::raw('borrowed_stock + 1'),
    ]);
}
echo 'Peminjaman demo PJM-DEMO-7B dibuat (jatuh tempo 3 hari lalu)'.PHP_EOL;
