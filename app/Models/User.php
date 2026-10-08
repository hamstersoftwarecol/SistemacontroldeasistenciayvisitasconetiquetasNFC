<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'email', 'password', 'role', 'permissions', 'employee_code', 'phone',
    'position', 'department', 'nfc_card_uid', 'shift_start', 'shift_end', 'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'permissions' => 'array',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'general_chat_read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            $user->nfc_card_uid = self::normalizeUid($user->nfc_card_uid);
        });
    }

    public static function normalizeUid(?string $uid): ?string
    {
        if ($uid === null) {
            return null;
        }

        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $uid));

        return $normalized === '' ? null : $normalized;
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function todayAttendance(): HasOne
    {
        return $this->hasOne(Attendance::class)->whereDate('date', today());
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function latestScan(): HasOne
    {
        return $this->hasOne(Scan::class)->latestOfMany('scanned_at');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function assignedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'assigned_to');
    }

    public function readBroadcasts(): BelongsToMany
    {
        return $this->belongsToMany(Broadcast::class, 'broadcast_reads')->withPivot('read_at');
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * @return list<string>
     */
    public function effectivePermissions(): array
    {
        if ($this->isAdmin()) {
            return array_keys(Permission::all());
        }

        $permissions = is_array($this->permissions)
            ? $this->permissions
            : ($this->role ?? Role::Employee)->defaultPermissions();

        return array_values(array_intersect($permissions, array_keys(Permission::all())));
    }

    public function hasCustomPermissions(): bool
    {
        return is_array($this->permissions) && ! $this->isAdmin();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->effectivePermissions(), true);
    }

    public function roleLabel(): string
    {
        return ($this->role ?? Role::Employee)->label();
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(5));
    }
}
