<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('nfc:admin {email : Correo del administrador} {--name= : Nombre} {--password= : Contraseña (se genera si se omite)}')]
#[Description('Crea un administrador o promueve a administrador a un usuario existente')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Correo no válido.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(14);
        $user = User::query()->firstWhere('email', $email);

        if ($user) {
            $user->forceFill(['role' => Role::Admin, 'is_active' => true, 'permissions' => null])->save();
            if ($this->option('password')) {
                $user->forceFill(['password' => $password])->save();
            }
            $this->info("{$user->name} ahora es administrador.");
        } else {
            $user = User::create([
                'name' => $this->option('name') ?: Str::before($email, '@'),
                'email' => $email,
                'password' => $password,
                'role' => Role::Admin,
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->info("Administrador creado: {$email}");
        }

        if (! $this->option('password') && $user->wasRecentlyCreated) {
            $this->warn("Contraseña generada: {$password}");
        }

        return self::SUCCESS;
    }
}
