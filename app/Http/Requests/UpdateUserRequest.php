<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Services\UserSafetyService;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends StoreUserRequest
{
    protected function currentUserId(): ?int
    {
        return $this->route('user')?->id;
    }

    /** Password diubah lewat fitur Reset Password, bukan di formulir ubah. */
    protected function extraRules(): array
    {
        return [];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('role')) {
                    return;
                }

                $target = $this->route('user');
                $newRole = Role::tryFrom((string) $this->input('role'));

                if (! $target || ! $newRole) {
                    return;
                }

                $blocker = app(UserSafetyService::class)->roleChangeBlocker($target, $newRole, $this->user());

                if ($blocker !== null) {
                    $validator->errors()->add('role', $blocker);
                }
            },
        ];
    }
}
