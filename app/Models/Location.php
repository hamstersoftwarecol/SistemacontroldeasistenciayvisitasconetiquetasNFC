<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'code', 'area', 'floor', 'description', 'is_active'])]
#[Hidden(['token', 'public_token'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'written_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Location $location) {
            $location->token ??= self::newToken();
            $location->public_token ??= self::newToken('public_token');
            $location->code ??= Str::upper(Str::random(6));
        });
    }

    public static function newToken(string $column = 'token'): string
    {
        do {
            $token = Str::random(32);
        } while (static::query()->where($column, $token)->exists());

        return $token;
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * URL que se graba en la etiqueta NFC.
     */
    public function tapUrl(): string
    {
        return route('tap', $this->token);
    }

    /**
     * Formulario público para reportar quejas desde esta ubicación.
     */
    public function complaintUrl(): string
    {
        return route('public.complaints.create', $this->public_token);
    }

    public function fullName(): string
    {
        return collect([$this->name, $this->area, $this->floor ? "Piso {$this->floor}" : null])
            ->filter()
            ->implode(' · ');
    }

    /**
     * Extrae el token de una URL de etiqueta (o devuelve el texto si ya es un token).
     */
    public static function tokenFromText(string $text): ?string
    {
        $text = trim($text);

        if (preg_match('#/t/([A-Za-z0-9]{16,64})#', $text, $matches)) {
            return $matches[1];
        }

        return preg_match('/^[A-Za-z0-9]{16,64}$/', $text) ? $text : null;
    }
}
