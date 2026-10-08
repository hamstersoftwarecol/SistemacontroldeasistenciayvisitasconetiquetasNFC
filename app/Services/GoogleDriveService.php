<?php

namespace App\Services;

use App\Models\Setting;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Subida de copias de seguridad a Google Drive usando OAuth 2.0 (alcance drive.file).
 */
class GoogleDriveService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD_API = 'https://www.googleapis.com/upload/drive/v3';

    private const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    public function isConfigured(): bool
    {
        return filled(Setting::get('gdrive_client_id')) && filled(Setting::get('gdrive_client_secret'));
    }

    public function isConnected(): bool
    {
        return $this->isConfigured() && filled(Setting::get('gdrive_refresh_token'));
    }

    public function isEnabled(): bool
    {
        return Setting::bool('gdrive_enabled') && $this->isConnected();
    }

    public function redirectUri(): string
    {
        return route('backups.google.callback');
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => Setting::get('gdrive_client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Canjea el código de autorización y guarda el refresh token.
     */
    public function connect(string $code): void
    {
        $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => Setting::get('gdrive_client_id'),
            'client_secret' => Setting::get('gdrive_client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if ($response->failed() || blank($response->json('refresh_token'))) {
            throw new RuntimeException('Google no devolvió un token de actualización: '.($response->json('error_description') ?? $response->json('error') ?? $response->status()));
        }

        Setting::set('gdrive_refresh_token', $response->json('refresh_token'));
        Cache::put('gdrive_access_token', $response->json('access_token'), now()->addSeconds(max(60, (int) $response->json('expires_in', 3600) - 120)));

        $about = Http::withToken($response->json('access_token'))->timeout(30)
            ->get(self::API.'/about', ['fields' => 'user(emailAddress,displayName)']);

        Setting::set('gdrive_account', $about->json('user.emailAddress') ?? '');
        Setting::set('gdrive_folder_id', '');
        Setting::set('gdrive_enabled', true);

        $this->folderId();
    }

    public function disconnect(): void
    {
        $token = Setting::get('gdrive_refresh_token');

        if (filled($token)) {
            rescue(fn () => Http::asForm()->timeout(15)->post(self::REVOKE_URL, ['token' => $token]), report: false);
        }

        Cache::forget('gdrive_access_token');
        Setting::setMany([
            'gdrive_refresh_token' => '',
            'gdrive_folder_id' => '',
            'gdrive_account' => '',
            'gdrive_enabled' => false,
        ]);
    }

    public function accessToken(): string
    {
        $cached = Cache::get('gdrive_access_token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, [
            'client_id' => Setting::get('gdrive_client_id'),
            'client_secret' => Setting::get('gdrive_client_secret'),
            'refresh_token' => Setting::get('gdrive_refresh_token'),
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed() || blank($response->json('access_token'))) {
            throw new RuntimeException('No se pudo renovar el acceso a Google Drive ('.($response->json('error') ?? $response->status()).'). Vuelve a conectar la cuenta.');
        }

        Cache::put('gdrive_access_token', $response->json('access_token'), now()->addSeconds(max(60, (int) $response->json('expires_in', 3600) - 120)));

        return $response->json('access_token');
    }

    /**
     * Carpeta de destino: se crea la primera vez (la app solo puede ver lo que ella misma crea).
     */
    public function folderId(): string
    {
        $folderId = (string) Setting::get('gdrive_folder_id');

        if ($folderId !== '') {
            $check = Http::withToken($this->accessToken())->timeout(30)
                ->get(self::API."/files/{$folderId}", ['fields' => 'id,trashed']);

            if ($check->successful() && ! $check->json('trashed')) {
                return $folderId;
            }
        }

        $response = Http::withToken($this->accessToken())->timeout(30)->post(self::API.'/files?fields=id', [
            'name' => Setting::get('gdrive_folder_name') ?: 'Copias AsistenciaNFC',
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('No se pudo crear la carpeta en Google Drive: '.$response->json('error.message', (string) $response->status()));
        }

        Setting::set('gdrive_folder_id', $response->json('id'));

        return $response->json('id');
    }

    /**
     * Sube un archivo con una carga reanudable y devuelve su id en Drive.
     */
    public function upload(string $path, string $name, string $mime = 'application/zip'): string
    {
        $size = filesize($path);

        $session = Http::withToken($this->accessToken())
            ->timeout(60)
            ->withHeaders([
                'X-Upload-Content-Type' => $mime,
                'X-Upload-Content-Length' => (string) $size,
            ])
            ->post(self::UPLOAD_API.'/files?uploadType=resumable&fields=id', [
                'name' => $name,
                'parents' => [$this->folderId()],
                'mimeType' => $mime,
            ]);

        $location = $session->header('Location');

        if ($session->failed() || blank($location)) {
            throw new RuntimeException('Google Drive rechazó la carga: '.$session->json('error.message', (string) $session->status()));
        }

        $handle = fopen($path, 'rb');

        try {
            $upload = Http::withToken($this->accessToken())
                ->timeout(600)
                ->withBody(Utils::streamFor($handle), $mime)
                ->put($location);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($upload->failed() || blank($upload->json('id'))) {
            throw new RuntimeException('Falló la subida a Google Drive: '.$upload->json('error.message', (string) $upload->status()));
        }

        return $upload->json('id');
    }

    public function delete(string $fileId): void
    {
        Http::withToken($this->accessToken())->timeout(30)->delete(self::API."/files/{$fileId}");
    }
}
