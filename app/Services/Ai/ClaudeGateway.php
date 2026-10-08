<?php

namespace App\Services\Ai;

use Anthropic\Client;
use App\Models\Setting;
use Psr\Http\Client\ClientInterface;
use RuntimeException;

/**
 * Envoltorio mínimo sobre el SDK oficial de Anthropic (Claude).
 */
class ClaudeGateway
{
    /** Transporte HTTP alternativo (PSR-18), usado en pruebas. */
    public ?ClientInterface $transporter = null;

    public function apiKey(): ?string
    {
        $key = Setting::get('ai_api_key') ?: config('services.anthropic.key');

        return filled($key) ? (string) $key : null;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    public function model(): string
    {
        return (string) (Setting::get('ai_model') ?: 'claude-opus-5-5');
    }

    /**
     * @param  list<array<string, mixed>>  $system
     * @param  list<array<string, mixed>>  $tools
     * @param  list<array<string, mixed>>  $messages
     */
    public function send(array $system, array $tools, array $messages): AssistantTurn
    {
        $apiKey = $this->apiKey() ?? throw new RuntimeException('Falta la clave de API de Claude. Configúrala en Ajustes → Asistente IA.');
        $model = $this->model();

        $client = new Client(apiKey: $apiKey, requestOptions: array_filter([
            'timeout' => 120,
            'maxRetries' => 2,
            'transporter' => $this->transporter,
        ]));

        $params = [
            'model' => $model,
            'maxTokens' => 16000,
            'system' => $system,
            'tools' => $tools,
            'messages' => $messages,
            'cacheControl' => ['type' => 'ephemeral'],
            'outputConfig' => ['effort' => Setting::get('ai_effort') ?: 'medium'],
        ];

        // Si el modelo rechaza la solicitud por política, el servidor la reintenta con el modelo alternativo recomendado.
        if (in_array($model, config('nfc.ai_fallback_models', []), true)) {
            $params['fallbacks'] = 'default';
            $params['betas'] = ['server-side-fallback-2026-07-01'];
        }

        $response = $client->beta->messages->create(...$params);

        $text = '';
        $toolUses = [];

        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            } elseif ($block->type === 'tool_use') {
                $toolUses[] = ['id' => $block->id, 'name' => $block->name, 'input' => (array) $block->input];
            }
        }

        return new AssistantTurn($response->stopReason, $text, $toolUses, $response->content, $response->model);
    }
}
