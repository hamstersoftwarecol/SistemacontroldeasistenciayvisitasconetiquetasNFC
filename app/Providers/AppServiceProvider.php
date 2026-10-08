<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\Broadcast;
use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        // URLs en español: /ubicaciones/crear, /usuarios/5/editar...
        Route::resourceVerbs(['create' => 'crear', 'edit' => 'editar']);

        // Cada clave de permiso es también una "ability" de Gate: @can('reports.view'), can:reports.view, etc.
        Gate::before(function (User $user, string $ability) {
            if (Permission::exists($ability)) {
                return $user->hasPermission($ability);
            }

            return null;
        });

        $this->applyMailSettings();

        RateLimiter::for('nfc-tap', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('public-complaints', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        View::composer('layouts.app', function ($view) {
            $user = Auth::user();

            $view->with('companyName', Setting::get('company_name'));

            if (! $user) {
                return;
            }

            try {
                $view->with('unreadBroadcasts', Broadcast::query()->unreadBy($user)->latest()->get());
                $view->with('unreadChat', $user->hasPermission('chat.use') ? $this->unreadChatCount($user) : 0);
            } catch (Throwable) {
                $view->with('unreadBroadcasts', collect());
                $view->with('unreadChat', 0);
            }
        });
    }

    private function unreadChatCount(User $user): int
    {
        $direct = Message::query()->where('recipient_id', $user->id)->whereNull('read_at')->count();

        $general = Message::query()
            ->whereNull('recipient_id')
            ->where('sender_id', '!=', $user->id)
            ->when($user->general_chat_read_at, fn ($q) => $q->where('created_at', '>', $user->general_chat_read_at))
            ->count();

        return $direct + $general;
    }

    /**
     * Aplica la configuración SMTP guardada desde el panel de Ajustes.
     */
    private function applyMailSettings(): void
    {
        if (! Setting::bool('mail_enabled') || blank(Setting::get('mail_host'))) {
            return;
        }

        $encryption = Setting::get('mail_encryption');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.host' => Setting::get('mail_host'),
            'mail.mailers.smtp.port' => Setting::int('mail_port', 587),
            'mail.mailers.smtp.username' => Setting::get('mail_username') ?: null,
            'mail.mailers.smtp.password' => Setting::get('mail_password') ?: null,
            'mail.mailers.smtp.timeout' => 15,
            'mail.from.address' => Setting::get('mail_from_address') ?: Setting::get('mail_username'),
            'mail.from.name' => Setting::get('mail_from_name') ?: Setting::get('company_name'),
        ]);
    }
}
