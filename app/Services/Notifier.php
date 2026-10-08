<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Envía notificaciones por correo sin interrumpir el flujo si el SMTP falla.
 */
class Notifier
{
    /**
     * @param  User|iterable<User>  $users
     */
    public function send(User|iterable $users, Notification $notification): void
    {
        $users = $users instanceof User ? collect([$users]) : collect($users);
        $users = $users->filter(fn (User $user) => $user->is_active && filled($user->email));

        if ($users->isEmpty()) {
            return;
        }

        try {
            NotificationFacade::send($users, $notification);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function toEmail(?string $email, Notification $notification): void
    {
        if (blank($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            NotificationFacade::route('mail', $email)->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function toPermission(string $permission, Notification $notification, ?User $except = null): void
    {
        $this->send($this->usersWithPermission($permission, $except), $notification);
    }

    /**
     * @return Collection<int, User>
     */
    public function usersWithPermission(string $permission, ?User $except = null): Collection
    {
        return User::query()->active()->get()
            ->filter(fn (User $user) => $user->hasPermission($permission))
            ->when($except, fn (Collection $users) => $users->reject(fn (User $user) => $user->is($except)))
            ->values();
    }
}
