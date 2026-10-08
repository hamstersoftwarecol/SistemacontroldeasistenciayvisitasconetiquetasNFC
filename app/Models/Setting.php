<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    private const CACHE_KEY = 'app_settings';

    /** @var array<string, string|null>|null */
    private static ?array $loaded = null;

    /**
     * Devuelve todos los valores guardados (sin descifrar), usando caché.
     *
     * @return array<string, string|null>
     */
    public static function stored(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }

        try {
            return self::$loaded = Cache::rememberForever(
                self::CACHE_KEY,
                fn () => static::query()->pluck('value', 'key')->all()
            );
        } catch (Throwable) {
            // Base de datos aún no migrada: se usan los valores por defecto.
            return [];
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $stored = self::stored();

        if (array_key_exists($key, $stored) && $stored[$key] !== null && $stored[$key] !== '') {
            $value = $stored[$key];

            if (self::isEncrypted($key)) {
                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    return $default;
                }
            }

            return $value;
        }

        return $default ?? config("nfc.defaults.{$key}");
    }

    public static function bool(string $key): bool
    {
        return filter_var(self::get($key), FILTER_VALIDATE_BOOLEAN);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $value = $value === null ? null : (string) $value;

        if ($value !== null && $value !== '' && self::isEncrypted($key)) {
            $value = Crypt::encryptString($value);
        }

        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        self::flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::set($key, $value);
        }
    }

    public static function flush(): void
    {
        self::$loaded = null;
        Cache::forget(self::CACHE_KEY);
    }

    public static function isEncrypted(string $key): bool
    {
        return in_array($key, config('nfc.encrypted', []), true);
    }

    /**
     * Días laborables según ISO-8601 (1 = lunes ... 7 = domingo).
     *
     * @return list<int>
     */
    public static function workingDays(): array
    {
        return array_values(array_filter(
            array_map('intval', explode(',', (string) self::get('working_days'))),
            fn (int $day) => $day >= 1 && $day <= 7
        ));
    }
}
