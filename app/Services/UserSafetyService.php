<?php

namespace App\Services;

use App\Enums\FineStatus;
use App\Enums\Role;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\User;

/**
 * Aturan keamanan akun pengguna. Setiap method mengembalikan pesan alasan
 * jika tindakan harus ditolak, atau null jika boleh.
 */
class UserSafetyService
{
    /** Apakah $user adalah satu-satunya Super Admin yang masih aktif? */
    public function isLastActiveSuperAdmin(User $user): bool
    {
        return $user->role === Role::SuperAdmin
            && $user->is_active
            && ! User::query()
                ->role(Role::SuperAdmin)->active()
                ->whereKeyNot($user->getKey())
                ->exists();
    }

    public function hasOpenBorrowings(User $user): bool
    {
        return Borrowing::query()->forMember($user->id)->open()->exists();
    }

    public function hasUnpaidFines(User $user): bool
    {
        return Fine::query()
            ->where('member_id', $user->id)
            ->where('status', FineStatus::Unpaid->value)
            ->exists();
    }

    public function deletionBlocker(User $user, ?User $actor): ?string
    {
        return match (true) {
            $actor?->is($user) => 'Anda tidak dapat menghapus akun Anda sendiri.',
            $this->isLastActiveSuperAdmin($user) => 'Super Admin aktif terakhir tidak dapat dihapus.',
            $this->hasOpenBorrowings($user) => 'Pengguna masih memiliki peminjaman yang belum selesai. Selesaikan dulu atau nonaktifkan akunnya.',
            $this->hasUnpaidFines($user) => 'Pengguna masih memiliki denda yang belum dibayar. Selesaikan dulu atau nonaktifkan akunnya.',
            default => null,
        };
    }

    public function deactivationBlocker(User $user, ?User $actor): ?string
    {
        return match (true) {
            $actor?->is($user) => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            $this->isLastActiveSuperAdmin($user) => 'Super Admin aktif terakhir tidak dapat dinonaktifkan.',
            default => null,
        };
    }

    public function roleChangeBlocker(User $user, Role $newRole, ?User $actor): ?string
    {
        if ($user->role === $newRole) {
            return null;
        }

        return match (true) {
            $actor?->is($user) => 'Anda tidak dapat mengubah role akun Anda sendiri.',
            $this->isLastActiveSuperAdmin($user) => 'Role Super Admin aktif terakhir tidak dapat diubah.',
            Borrowing::query()->forMember($user->id)->exists() => 'Pengguna memiliki riwayat peminjaman sehingga role-nya tidak dapat diubah.',
            default => null,
        };
    }
}
