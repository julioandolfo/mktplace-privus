<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        {{-- Google Fonts: Inter + JetBrains Mono --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased" x-data="{ sidebarOpen: true, mobileSidebar: false }">
        <div class="min-h-screen flex bg-surface-950">
            {{-- Sidebar --}}
            @include('components.layout.sidebar')

            {{-- Mobile overlay --}}
            <div x-show="mobileSidebar"
                 x-transition:enter="transition-opacity ease-smooth duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-smooth duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="mobileSidebar = false"
                 class="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm lg:hidden">
            </div>

            {{-- Main content wrapper --}}
            <div class="flex-1 flex flex-col min-w-0 transition-all duration-300 ease-smooth"
                 :class="sidebarOpen ? 'lg:ml-[280px]' : 'lg:ml-[72px]'">

                {{-- Topbar --}}
                @include('components.layout.topbar')

                {{-- Breadcrumbs --}}
                @isset($breadcrumbs)
                <nav class="px-6 py-3 border-b border-surface-800/50">
                    <ol class="flex items-center gap-2 text-sm text-surface-500">
                        <li>
                            <a href="{{ route('dashboard') }}" class="hover:text-primary-400 transition-colors flex items-center gap-1">
                                <x-heroicon-o-home class="w-4 h-4" />
                            </a>
                        </li>
                        <li class="text-surface-600">/</li>
                        {{ $breadcrumbs }}
                    </ol>
                </nav>
                @endisset

                {{-- Page header --}}
                @isset($header)
                <header class="px-6 py-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="page-title">{{ $header }}</h1>
                            @isset($subtitle)
                            <p class="page-subtitle">{{ $subtitle }}</p>
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
                <main class="flex-1 px-6 pb-8">
                    {{-- Flash messages --}}
                    @if (session('success'))
                    <div class="mb-6 alert-success" x-data="{ show: true }" x-show="show" x-transition>
                        <x-heroicon-s-check-circle class="w-5 h-5 flex-shrink-0" />
                        <div class="flex-1">{{ session('success') }}</div>
                        <button @click="show = false" class="text-emerald-400/60 hover:text-emerald-400 transition-colors">
                            <x-heroicon-s-x-mark class="w-4 h-4" />
                        </button>
                    </div>
                    @endif

                    @if (session('error'))
                    <div class="mb-6 alert-error" x-data="{ show: true }" x-show="show" x-transition>
                        <x-heroicon-s-exclamation-circle class="w-5 h-5 flex-shrink-0" />
                        <div class="flex-1">{{ session('error') }}</div>
                        <button @click="show = false" class="text-red-400/60 hover:text-red-400 transition-colors">
                            <x-heroicon-s-x-mark class="w-4 h-4" />
                        </button>
                    </div>
                    @endif

                    @if (session('warning'))
                    <div class="mb-6 alert-warning" x-data="{ show: true }" x-show="show" x-transition>
                        <x-heroicon-s-exclamation-triangle class="w-5 h-5 flex-shrink-0" />
                        <div class="flex-1">{{ session('warning') }}</div>
                        <button @click="show = false" class="text-amber-400/60 hover:text-amber-400 transition-colors">
                            <x-heroicon-s-x-mark class="w-4 h-4" />
                        </button>
                    </div>
                    @endif

                    {{-- Slot content --}}
                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts
        @stack('scripts')
    </body>
</html>
