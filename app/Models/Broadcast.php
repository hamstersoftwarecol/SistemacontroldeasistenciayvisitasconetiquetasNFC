<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'title', 'body', 'audience', 'priority', 'send_email'])]
class Broadcast extends Model
{
    public const AUDIENCES = [
        'all' => 'Todo el equipo',
        'employee' => 'Solo empleados',
        'supervisor' => 'Solo supervisores',
        'admin' => 'Solo administradores',
    ];

    public const PRIORITIES = [
        'normal' => 'Normal',
        'important' => 'Importante',
        'urgent' => 'Urgente',
    ];

    protected function casts(): array
    {
        return [
            'send_email' => 'boolean',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'broadcast_reads')->withPivot('read_at');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('audience', 'all')
                ->orWhere('audience', $user->role->value)
                ->orWhere('user_id', $user->id);
        });
    }

    public function scopeUnreadBy(Builder $query, User $user): Builder
    {
        return $query->visibleTo($user)
            ->where('created_at', '>=', $user->created_at)
            ->whereDoesntHave('readers', fn (Builder $q) => $q->whereKey($user->id));
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    /**
     * @return Builder<User>
     */
    public function recipientsQuery(): Builder
    {
        return User::query()->active()
            ->when($this->audience !== 'all', fn (Builder $q) => $q->where('role', $this->audience));
    }
}
