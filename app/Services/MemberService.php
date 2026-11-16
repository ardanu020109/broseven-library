<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\MemberProfile;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MemberService
{
    private const MAX_NUMBER_RETRIES = 5;

    /**
     * Membuat akun anggota beserta profil keanggotaannya dalam satu transaksi.
     * Role SELALU anggota, apa pun isi $data.
     *
     * @param  array<string, mixed>  $data  name, username, email, password, phone?, address?
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = new User(Arr::only($data, ['name', 'username', 'email', 'password', 'phone', 'address']));
            $user->role = Role::Anggota;
            $user->is_active = true;
            $user->save();

            $this->createProfile($user);

            return $user;
        });
    }

    /**
     * Nomor anggota dihitung dari nilai terbesar + 1. Jika dua registrasi bersamaan
     * menghasilkan nomor yang sama, constraint UNIQUE menolak dan kita coba lagi.
     */
    public function createProfile(User $user): MemberProfile
    {
        $months = (int) Setting::get('member_validity_months', 12);
        $joined = today();

        for ($attempt = 1; ; $attempt++) {
            try {
                return $user->memberProfile()->create([
                    'member_number' => MemberProfile::generateMemberNumber(),
                    'joined_at' => $joined->toDateString(),
                    'expired_at' => $joined->copy()->addMonths($months)->toDateString(),
                    'status' => MemberProfile::STATUS_ACTIVE,
                    'card_token' => MemberProfile::generateCardToken(),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::MAX_NUMBER_RETRIES) {
                    throw $e;
                }
            }
        }
    }
}
