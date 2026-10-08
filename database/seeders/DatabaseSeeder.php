<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Crea el administrador inicial. Usa ADMIN_EMAIL / ADMIN_PASSWORD del .env si existen.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');

        $admin = User::query()->firstOrCreate(['email' => $email], [
            'name' => env('ADMIN_NAME', 'Administrador'),
            'password' => env('ADMIN_PASSWORD', 'password'),
            'role' => Role::Admin,
            'is_active' => true,
            'employee_code' => 'ADM-001',
        ]);

        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->command?->info("Administrador: {$email}");
    }
}
