<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['ai_conversation_id', 'role', 'content', 'tools_used'])]
class AiMessage extends Model
{
    protected function casts(): array
    {
        return [
            'tools_used' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function html(): string
    {
        return (string) Str::markdown($this->content, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
