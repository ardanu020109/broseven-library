<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemberProfile extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'member_number',
        'joined_at',
        'expired_at',
        'status',
        'card_token',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'expired_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Keanggotaan dianggap berlaku jika status aktif dan belum lewat masa berlaku. */
    protected function isValid(): Attribute
    {
        return Attribute::get(fn () => $this->status === self::STATUS_ACTIVE
            && ($this->expired_at === null || $this->expired_at->gte(today())));
    }

    /** Format: BS + 2 digit tahun + 5 digit urut, contoh BS2600001. */
    public static function generateMemberNumber(): string
    {
        $prefix = 'BS' . now()->format('y');
        $last = static::query()->where('member_number', 'like', $prefix . '%')->max('member_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /** Token acak untuk QR Code (tidak menampilkan data pribadi). */
    public static function generateCardToken(): string
    {
        return Str::random(64);
    }
}
