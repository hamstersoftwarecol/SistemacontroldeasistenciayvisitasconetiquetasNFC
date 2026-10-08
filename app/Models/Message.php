<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sender_id', 'recipient_id', 'body', 'read_at'])]
class Message extends Model
{
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toChatArray(User $viewer): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'mine' => $this->sender_id === $viewer->id,
            'sender' => $this->sender?->name,
            'initials' => $this->sender?->initials(),
            'time' => $this->created_at->format('H:i'),
            'date' => $this->created_at->translatedFormat('d M Y'),
            'read' => $this->read_at !== null,
        ];
    }
}
