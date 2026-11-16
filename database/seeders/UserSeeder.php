<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public const PASSWORD = 'Password123!';

    public function run(): void
    {
        $this->upsert('Super Admin', 'superadmin', 'superadmin@broseven.test', Role::SuperAdmin);
        $this->upsert('Admin Master', 'adminmaster', 'adminmaster@broseven.test', Role::AdminMaster);
        $this->upsert('Admin Karyawan', 'adminkaryawan', 'adminkaryawan@broseven.test', Role::AdminKaryawan);

        $members = [
            ['Budi Santoso', 'budi.santoso', 'budi@broseven.test'],
            ['Siti Rahayu', 'siti.rahayu', 'siti@broseven.test'],
            ['Andi Pratama', 'andi.pratama', 'andi@broseven.test'],
            ['Dewi Lestari', 'dewi.lestari', 'dewi@broseven.test'],
            ['Rizky Maulana', 'rizky.maulana', 'rizky@broseven.test'],
        ];

        foreach ($members as $i => [$name, $username, $email]) {
            $user = $this->upsert(
                $name,
                $username,
                $email,
                Role::Anggota,
                '08120000000' . ($i + 1),
                'Jl. Contoh No. ' . ($i + 1),
            );

            $profile = MemberProfile::query()->firstOrNew(['user_id' => $user->id]);

            if (! $profile->exists) {
                $joined = now()->subMonths($i + 1)->startOfDay();

                $profile->fill([
                    'member_number' => MemberProfile::generateMemberNumber(),
                    'joined_at' => $joined->toDateString(),
                    'expired_at' => $joined->copy()->addMonths(12)->toDateString(),
                    'status' => MemberProfile::STATUS_ACTIVE,
                    'card_token' => MemberProfile::generateCardToken(),
                ])->save();
            }
        }
    }

    private function upsert(
        string $name,
        string $username,
        string $email,
        Role $role,
        ?string $phone = null,
        ?string $address = null,
    ): User {
        $user = User::withTrashed()->firstOrNew(['username' => $username]);

        // forceFill karena "role" dan "is_active" sengaja tidak mass-assignable.
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => self::PASSWORD, // di-hash otomatis oleh cast "hashed"
            'phone' => $phone,
            'address' => $address,
            'role' => $role,
            'is_active' => true,
        ])->save();

        return $user;
    }
}
