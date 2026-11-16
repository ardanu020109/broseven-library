<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LibraryProfileRequest;
use App\Models\ActivityLog;
use App\Models\LibraryProfile;
use App\Services\ImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class LibraryProfileController extends Controller
{
    public function __construct(private readonly ImageUploader $images)
    {
    }

    public function edit(): View
    {
        Gate::authorize('manage-library');

        return view('super-admin.library-profile.edit', [
            'profile' => LibraryProfile::query()->first() ?? LibraryProfile::current(),
            'networks' => LibraryProfileRequest::NETWORKS,
        ]);
    }

    public function update(LibraryProfileRequest $request): RedirectResponse
    {
        Gate::authorize('manage-library');

        $data = $request->validated();
        $profile = LibraryProfile::query()->first() ?? new LibraryProfile();

        $newLogo = $request->hasFile('logo') ? $this->images->store($request->file('logo'), 'logos') : null;
        $logoChanged = $newLogo !== null || $request->boolean('remove_logo');
        $oldLogo = $profile->logo;

        // Hanya tautan yang diisi yang disimpan.
        $social = collect($data['social'] ?? [])->filter(fn ($url) => filled($url))->all();

        $attributes = Arr::except($data, ['logo', 'remove_logo', 'social']);
        $attributes['social_media'] = $social !== [] ? $social : null;

        if ($newLogo !== null) {
            $attributes['logo'] = $newLogo;
        } elseif ($request->boolean('remove_logo')) {
            $attributes['logo'] = null;
        }

        try {
            DB::transaction(fn () => $profile->fill($attributes)->save());
        } catch (Throwable $e) {
            $this->images->delete($newLogo);

            throw $e;
        }

        if ($logoChanged) {
            $this->images->delete($oldLogo);
        }

        ActivityLog::record('library.updated', 'Memperbarui profil perpustakaan', $profile);

        return redirect()->route('super-admin.library-profile.edit')
            ->with('success', 'Profil perpustakaan berhasil diperbarui.');
    }
}
