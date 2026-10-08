<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#4f46e5">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" href="{{ asset('icon.svg') }}" type="image/svg+xml">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ \App\Models\Setting::get('company_name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="flex min-h-screen flex-col items-center bg-gradient-to-br from-indigo-50 via-white to-violet-50 px-4 pt-8 sm:justify-center sm:pt-0">
            <a href="/" class="flex flex-col items-center gap-2">
                <x-application-logo class="h-16 w-16 text-indigo-600" />
                <span class="text-lg font-bold text-gray-900">{{ \App\Models\Setting::get('company_name') }}</span>
                <span class="text-xs text-gray-500">Control de asistencia NFC</span>
            </a>

            <div class="mt-6 w-full overflow-hidden rounded-2xl border border-gray-200 bg-white px-6 py-6 shadow-xl sm:max-w-md">
                {{ $slot }}
            </div>

            <p class="mt-6 pb-6 text-xs text-gray-400">Primer toque = entrada · Último toque = salida</p>
        </div>
    </body>
</html>
