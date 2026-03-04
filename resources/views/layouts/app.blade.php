<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ darkMode: localStorage.getItem('theme') !== 'light' }" x-init="$watch('darkMode', val => { localStorage.setItem('theme', val ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', val) })" :class="{ 'dark': darkMode }">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        {{-- Google Fonts: Fira Sans + Fira Code --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased" x-data="{ sidebarOpen: true, mobileSidebar: false }">
        <div class="min-h-screen flex">
            {{-- Sidebar --}}
            @include('components.layout.sidebar')

            {{-- Mobile overlay --}}
            <div x-show="mobileSidebar" x-transition.opacity @click="mobileSidebar = false"
                 class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

            {{-- Main content --}}
            <div class="flex-1 flex flex-col min-w-0" :class="sidebarOpen ? 'lg:ml-64' : 'lg:ml-[4.5rem]'" style="transition: margin-left 200ms ease">
                {{-- Topbar --}}
                @include('components.layout.topbar')

                {{-- Breadcrumbs --}}
                @isset($breadcrumbs)
                <nav class="px-4 sm:px-6 lg:px-8 py-3">
                    <ol class="flex items-center gap-2 text-sm text-gray-500 dark:text-zinc-400">
                        <li><a href="{{ route('dashboard') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Dashboard</a></li>
                        {{ $breadcrumbs }}
                    </ol>
                </nav>
                @endisset

                {{-- Page header --}}
                @isset($header)
                <header class="px-4 sm:px-6 lg:px-8 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $header }}</h1>
                            @isset($subtitle)
                            <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">{{ $subtitle }}</p>
                            @endisset
                        </div>
                        @isset($actions)
                        <div class="flex items-center gap-3">
                            {{ $actions }}
                        </div>
                        @endisset
                    </div>
                </header>
                @endisset

                {{-- Main content --}}
                <main class="flex-1 px-4 sm:px-6 lg:px-8 pb-8">
                    {{-- Flash messages --}}
                    @if (session('success'))
                    <div class="mb-4 p-4 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400" x-data="{ show: true }" x-show="show" x-transition>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-heroicon-s-check-circle class="w-5 h-5" />
                                <span>{{ session('success') }}</span>
                            </div>
                            <button @click="show = false" class="text-emerald-500 hover:text-emerald-700">
                                <x-heroicon-s-x-mark class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                    @endif

                    @if (session('error'))
                    <div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400" x-data="{ show: true }" x-show="show" x-transition>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-heroicon-s-exclamation-circle class="w-5 h-5" />
                                <span>{{ session('error') }}</span>
                            </div>
                            <button @click="show = false" class="text-red-500 hover:text-red-700">
                                <x-heroicon-s-x-mark class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts
        @stack('scripts')
    </body>
</html>
