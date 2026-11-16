<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\ActivityLog;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\User;
use App\Services\ImageUploader;
use App\Services\MemberService;
use App\Services\UserSafetyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

class UserController extends Controller
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly MemberService $members,
        private readonly ImageUploader $images,
        private readonly UserSafetyService $safety,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('manage-users');

        $term = trim(Str::limit($request->string('q')->toString(), 100, ''));
        $role = $request->enum('role', Role::class);
        $status = $request->string('status')->toString();
        $status = in_array($status, ['active', 'inactive'], true) ? $status : '';

        $users = User::query()
            ->with('memberProfile:id,user_id,member_number')
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term) {
                $w->where('name', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            }))
            ->when($role, fn ($q) => $q->where('role', $role->value))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('super-admin.users.index', [
            'users' => $users,
            'roles' => $this->roleOptions(),
            'filters' => ['q' => $term, 'role' => $role?->value ?? '', 'status' => $status],
            'hasFilters' => $term !== '' || $role !== null || $status !== '',
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manage-users');

        return view('super-admin.users.form', [
            'user' => new User(['role' => Role::Anggota->value, 'is_active' => true]),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validated();
        $photo = $request->hasFile('photo') ? $this->images->store($request->file('photo'), 'avatars') : null;

        try {
            $user = DB::transaction(function () use ($data, $photo, $request) {
                $user = new User(Arr::only($data, ['name', 'username', 'email', 'password', 'phone', 'address']));
                $user->photo = $photo;

                // Role dan status sengaja tidak mass-assignable: selalu diset eksplisit.
                $user->role = Role::from($data['role']);
                $user->is_active = $request->boolean('is_active');
                $user->save();

                if ($user->role === Role::Anggota) {
                    $this->members->createProfile($user);
                }

                return $user;
            });
        } catch (Throwable $e) {
            $this->images->delete($photo);

            throw $e;
        }

        ActivityLog::record('user.created', "Menambah pengguna: {$user->username} ({$user->role->label()})", $user);

        return redirect()->route('super-admin.users.show', $user)->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function show(User $user): View
    {
        Gate::authorize('manage-users');

        $user->load('memberProfile');

        return view('super-admin.users.show', [
            'user' => $user,
            'stats' => [
                'borrowings' => Borrowing::query()->forMember($user->id)->count(),
                'open' => Borrowing::query()->forMember($user->id)->open()->count(),
                'fines_unpaid' => Fine::query()->where('member_id', $user->id)->where('status', 'unpaid')->count(),
                'processed' => $user->processedBorrowings()->count(),
            ],
            'activities' => $user->activityLogs()->latest()->latest('id')->take(10)->get(),
        ]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('manage-users');

        return view('super-admin.users.form', [
            'user' => $user,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validated();
        $oldRole = $user->role;
        $newPhoto = $request->hasFile('photo') ? $this->images->store($request->file('photo'), 'avatars') : null;
        $photoChanged = $newPhoto !== null || $request->boolean('remove_photo');
        $oldPhoto = $user->photo;

        try {
            DB::transaction(function () use ($user, $data, $newPhoto, $request) {
                $attributes = Arr::only($data, ['name', 'username', 'email', 'phone', 'address']);

                if ($newPhoto !== null) {
                    $attributes['photo'] = $newPhoto;
                } elseif ($request->boolean('remove_photo')) {
                    $attributes['photo'] = null;
                }

                $user->fill($attributes);
                $user->role = Role::from($data['role']);
                $user->save();

                if ($user->role === Role::Anggota && ! $user->memberProfile()->exists()) {
                    $this->members->createProfile($user);
                }
            });
        } catch (Throwable $e) {
            $this->images->delete($newPhoto);

            throw $e;
        }

        if ($photoChanged) {
            $this->images->delete($oldPhoto);
        }

        $description = "Mengubah pengguna: {$user->username}";
        if ($oldRole !== $user->role) {
            $description .= " (role {$oldRole->label()} menjadi {$user->role->label()})";
        }

        ActivityLog::record('user.updated', $description, $user);

        return redirect()->route('super-admin.users.show', $user)->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        $request->validate([
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()],
        ]);

        $user->forceFill([
            'password' => $request->input('password'), // di-hash oleh cast "hashed"
            'remember_token' => Str::random(60),
        ])->save();

        $this->revokeSessions($user, $request);

        ActivityLog::record('user.password_reset', "Mereset password pengguna: {$user->username}", $user);

        return redirect()->route('super-admin.users.show', $user)
            ->with('success', 'Password berhasil direset. Sesi login lain milik pengguna ini diakhiri.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        $activating = ! $user->is_active;

        if (! $activating && ($reason = $this->safety->deactivationBlocker($user, $request->user()))) {
            return redirect()->route('super-admin.users.show', $user)->with('error', $reason);
        }

        $user->forceFill(['is_active' => $activating])->save();

        if (! $activating) {
            $this->revokeSessions($user, $request);
        }

        ActivityLog::record(
            'user.status_changed',
            ($activating ? 'Mengaktifkan' : 'Menonaktifkan') . " pengguna: {$user->username}",
            $user,
        );

        return redirect()->route('super-admin.users.show', $user)
            ->with('success', $activating ? 'Akun berhasil diaktifkan.' : 'Akun berhasil dinonaktifkan.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        if ($reason = $this->safety->deletionBlocker($user, $request->user())) {
            return redirect()->route('super-admin.users.index')->with('error', $reason);
        }

        $username = $user->username;
        $id = $user->id;

        // Soft delete: riwayat transaksi pengguna tetap tersimpan.
        $user->delete();
        $this->revokeSessions($user, $request);

        ActivityLog::record('user.deleted', "Menghapus pengguna: {$username}", null, ['id' => $id, 'username' => $username]);

        return redirect()->route('super-admin.users.index')->with('success', 'Pengguna berhasil dihapus.');
    }

    /** @return array<string, string> */
    private function roleOptions(): array
    {
        return collect(Role::cases())->mapWithKeys(fn (Role $role) => [$role->value => $role->label()])->all();
    }

    /** Akhiri sesi login pengguna (hanya jika sesi disimpan di database). Sesi saat ini tidak ikut diakhiri. */
    private function revokeSessions(User $user, Request $request): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $query = DB::table(config('session.table', 'sessions'))->where('user_id', $user->id);

        if ($request->user()?->is($user)) {
            $query->where('id', '!=', $request->session()->getId());
        }

        $query->delete();
    }
}
