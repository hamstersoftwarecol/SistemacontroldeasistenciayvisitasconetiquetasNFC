<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'reporter_name', 'reporter_email', 'reporter_phone', 'location_id', 'category',
    'priority', 'subject', 'description', 'status', 'assigned_to', 'attachment_path', 'resolved_at',
])]
class Complaint extends Model
{
    public const CATEGORIES = [
        'general' => 'General',
        'cleaning' => 'Limpieza',
        'maintenance' => 'Mantenimiento',
        'service' => 'Atención / servicio',
        'safety' => 'Seguridad',
        'staff' => 'Personal',
        'equipment' => 'Equipos',
        'other' => 'Otro',
    ];

    public const PRIORITIES = [
        'low' => 'Baja',
        'medium' => 'Media',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ];

    public const STATUSES = [
        'open' => 'Abierta',
        'in_progress' => 'En proceso',
        'resolved' => 'Resuelta',
        'closed' => 'Cerrada',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Complaint $complaint) {
            $complaint->forceFill(['code' => 'QJ-'.str_pad((string) $complaint->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ComplaintUpdate::class)->orderBy('created_at');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }

    public function canBeViewedBy(User $user): bool
    {
        return $user->can('complaints.manage')
            || $this->user_id === $user->id
            || $this->assigned_to === $user->id;
    }

    public function reporterName(): string
    {
        return $this->user?->name ?? $this->reporter_name ?? 'Anónimo';
    }

    public function reporterEmail(): ?string
    {
        return $this->user?->email ?? $this->reporter_email;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'open' => 'amber',
            'in_progress' => 'sky',
            'resolved' => 'emerald',
            default => 'gray',
        };
    }

    public function priorityColor(): string
    {
        return match ($this->priority) {
            'urgent' => 'rose',
            'high' => 'orange',
            'medium' => 'amber',
            default => 'gray',
        };
    }
}
