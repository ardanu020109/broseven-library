<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    public const USERNAME_PATTERN = '/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/';
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
        'address',
        'photo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    // ---------- Relasi ----------

    public function memberProfile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    /** Peminjaman milik anggota ini. */
    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class, 'member_id');
    }

    /** Peminjaman yang diproses oleh petugas ini. */
    public function processedBorrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class, 'processed_by');
    }

    public function fines(): HasMany
    {
        return $this->hasMany(Fine::class, 'member_id');
    }

    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class, 'author_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // ---------- Scope ----------

    public function scopeRole(Builder $query, Role|string $role): Builder
    {
        return $query->where('role', $role instanceof Role ? $role->value : $role);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ---------- Helper role ----------

    public function hasRole(Role|string ...$roles): bool
    {
        foreach ($roles as $role) {
            $value = $role instanceof Role ? $role->value : $role;

            if ($this->role?->value === $value) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isAdminMaster(): bool
    {
        return $this->role === Role::AdminMaster;
    }

    public function isAdminKaryawan(): bool
    {
        return $this->role === Role::AdminKaryawan;
    }

    public function isAnggota(): bool
    {
        return $this->role === Role::Anggota;
    }

    // ---------- Accessor ----------

    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo
            ? asset('storage/' . $this->photo)
            : asset('assets/images/default-avatar.svg'));
    }
}
