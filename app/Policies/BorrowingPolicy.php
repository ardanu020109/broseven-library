<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Borrowing;
use App\Models\User;

class BorrowingPolicy
{
    /** Petugas melihat semua transaksi. Anggota hanya miliknya sendiri (proteksi IDOR). */
    public function view(User $user, Borrowing $borrowing): bool
    {
        return $this->isStaff($user) || $this->isOwner($user, $borrowing);
    }

    public function cancel(User $user, Borrowing $borrowing): bool
    {
        return $this->isOwner($user, $borrowing);
    }

    public function approve(User $user, Borrowing $borrowing): bool
    {
        return $this->isStaff($user);
    }

    public function reject(User $user, Borrowing $borrowing): bool
    {
        return $this->isStaff($user);
    }

    private function isStaff(User $user): bool
    {
        return $user->hasRole(Role::SuperAdmin, Role::AdminKaryawan);
    }

    private function isOwner(User $user, Borrowing $borrowing): bool
    {
        return $user->role === Role::Anggota && $borrowing->member_id === $user->id;
    }
}
