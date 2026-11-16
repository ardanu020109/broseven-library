<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\LibraryProfile;
use App\Models\MemberProfile;
use App\Models\User;
use App\Models\Fine;
use App\Models\ReturnItem;
use App\Models\ReturnTransaction;

trait CreatesLibraryData
{
    /** Anggota dengan keanggotaan yang berlaku. */
    protected function makeMember(array $userAttributes = [], array $profileAttributes = []): User
    {
        $user = User::factory()->create($userAttributes);

        MemberProfile::factory()->create(array_merge([
            'user_id' => $user->id,
            'joined_at' => today()->subMonth()->toDateString(),
            'expired_at' => today()->addYear()->toDateString(),
            'status' => MemberProfile::STATUS_ACTIVE,
        ], $profileAttributes));

        return $user;
    }

    protected function makeStaff(string $role = 'admin-karyawan'): User
    {
        return User::factory()->role(Role::from($role))->create();
    }

    protected function makeLibraryProfile(array $attributes = []): LibraryProfile
    {
        return LibraryProfile::create(array_merge([
            'name' => 'Perpustakaan Uji',
            'max_books_per_loan' => 3,
            'loan_duration_days' => 7,
            'fine_per_day' => 2000,
        ], $attributes));
    }

    /** Pengajuan berstatus pending berisi buku-buku (masing-masing 1 eksemplar). */
    protected function makePending(User $member, array $books, array $attributes = []): Borrowing
    {
        $borrowing = Borrowing::factory()->create(array_merge([
            'member_id' => $member->id,
            'status' => 'pending',
        ], $attributes));

        foreach ($books as $book) {
            BorrowingItem::factory()->create(['borrowing_id' => $borrowing->id, 'book_id' => $book->id, 'quantity' => 1]);
        }

        return $borrowing;
    }

    protected function makeBorrowed(User $member, User $staff, array $books, array $attributes = [], int $quantity = 1, int $returned = 0): Borrowing
    {
        $borrowing = Borrowing::factory()->create(array_merge([
            'member_id' => $member->id,
            'processed_by' => $staff->id,
            'status' => 'borrowed',
            'requested_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
            'borrow_date' => today()->subDay()->toDateString(),
            'due_date' => today()->addDays(6)->toDateString(),
        ], $attributes));

        foreach ($books as $book) {
            BorrowingItem::factory()->create([
                'borrowing_id' => $borrowing->id,
                'book_id' => $book->id,
                'quantity' => $quantity,
                'returned_quantity' => $returned,
            ]);
        }

        return $borrowing;
    }

    protected function stockOf(Book $book): array
    {
        $book->refresh();

        return [$book->total_stock, $book->available_stock, $book->borrowed_stock];
    }

    /** Denda yang sudah ada (peminjaman, pengembalian, dan item pengembalian lengkap). */
    protected function makeFine(User $member, User $staff, float $amount = 4000, array $fineAttributes = []): Fine
    {
        $book = Book::factory()->create();
        $borrowing = $this->makeBorrowed($member, $staff, [$book], [
            'status' => 'returned',
            'due_date' => today()->subDays(2)->toDateString(),
        ], 1, 1);

        $item = $borrowing->items()->firstOrFail();

        $return = ReturnTransaction::create([
            'code' => ReturnTransaction::generateCode(),
            'borrowing_id' => $borrowing->id,
            'return_date' => today()->toDateString(),
            'processed_by' => $staff->id,
        ]);

        $returnItem = ReturnItem::create([
            'return_id' => $return->id,
            'borrowing_item_id' => $item->id,
            'quantity' => 1,
            'due_date' => today()->subDays(2)->toDateString(),
            'days_late' => 2,
            'fine_per_day' => $amount / 2,
            'fine_amount' => $amount,
        ]);

        return Fine::create(array_merge([
            'return_item_id' => $returnItem->id,
            'member_id' => $member->id,
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ], $fineAttributes));
    }
}
