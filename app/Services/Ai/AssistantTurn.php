<?php

namespace App\Services\Ai;

/**
 * Respuesta normalizada del modelo para un paso del bucle de herramientas.
 */
final readonly class AssistantTurn
{
    /**
     * @param  list<array{id: string, name: string, input: array<string, mixed>}>  $toolUses
     * @param  array<int, mixed>  $rawContent  Contenido original para reenviarlo sin cambios como turno del asistente.
     */
    public function __construct(
        public ?string $stopReason,
        public string $text,
        public array $toolUses,
        public array $rawContent,
        public ?string $model = null,
    ) {}
}
