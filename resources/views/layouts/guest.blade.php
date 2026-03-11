<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ \App\Models\SystemSetting::get('general', 'system_name', config('app.name', 'MktPlace Privus')) }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-zinc-900 dark:to-zinc-950">

            {{-- Logo --}}
            <div class="mb-2">
                @php $loginLogo = \App\Models\SystemSetting::get('general', 'logo_url'); @endphp
                @if($loginLogo)
                    <a href="/">
                        <img src="{{ $loginLogo }}" alt="Logo" class="w-24 h-24 object-contain">
                    </a>
                @else
                    <a href="/" class="flex items-center gap-3">
                        <div class="w-14 h-14 rounded-2xl bg-primary-600 flex items-center justify-center shadow-lg">
                            <span class="text-white font-bold text-2xl">MP</span>
                        </div>
                    </a>
                @endif
            </div>

            {{-- System name --}}
            <h1 class="text-xl font-bold text-gray-700 dark:text-zinc-300 mb-4">
                {{ \App\Models\SystemSetting::get('general', 'system_name', 'MktPlace Privus') }}
            </h1>

            <div class="w-full sm:max-w-md px-6 py-6 bg-white dark:bg-zinc-800 shadow-xl overflow-hidden sm:rounded-2xl border border-gray-200 dark:border-zinc-700">
                {{ $slot }}
            </div>

            <p class="text-xs text-gray-400 dark:text-zinc-600 mt-6">&copy; {{ date('Y') }} {{ \App\Models\SystemSetting::get('general', 'system_name', 'MktPlace Privus') }}</p>
        </div>
    </body>
</html>
