<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\ActivityLog;
use App\Services\ImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly ImageUploader $images)
    {
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()->load('memberProfile')]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $newPhoto = $request->hasFile('photo') ? $this->images->store($request->file('photo'), 'avatars') : null;
        $photoChanged = $newPhoto !== null || $request->boolean('remove_photo');
        $oldPhoto = $user->photo;

        $attributes = Arr::only($data, ['name', 'phone', 'address']);

        if ($newPhoto !== null) {
            $attributes['photo'] = $newPhoto;
        } elseif ($request->boolean('remove_photo')) {
            $attributes['photo'] = null;
        }

        $user->fill($attributes)->save();

        if ($photoChanged) {
            $this->images->delete($oldPhoto);
        }

        ActivityLog::record('user.profile_updated', 'Memperbarui profil sendiri', $user);

        return redirect()->route('profile.edit')->with('success', 'Profil berhasil diperbarui.');
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', PasswordRule::min(8)->letters()->mixedCase()->numbers()],
        ], [], ['current_password' => 'password saat ini', 'password' => 'password baru']);

        $user = $request->user();

        $user->forceFill([
            'password' => $request->input('password'), // di-hash oleh cast "hashed"
            'remember_token' => Str::random(60),
        ])->save();

        // Akhiri sesi lain milik pengguna ini (jika sesi disimpan di database). Sesi saat ini dipertahankan.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        ActivityLog::record('auth.password_changed', 'Mengubah password sendiri', $user);

        return redirect()->route('profile.edit')->with('success', 'Password berhasil diubah.');
    }
}
