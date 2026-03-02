<header class="sticky top-0 z-20 h-16 bg-white dark:bg-zinc-800 border-b border-gray-200 dark:border-zinc-700 flex items-center justify-between px-4 sm:px-6">
    <div class="flex items-center gap-4">
        {{-- Mobile menu toggle --}}
        <button @click="mobileSidebar = true" class="lg:hidden text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-200">
            <x-heroicon-o-bars-3 class="w-6 h-6" />
        </button>

        {{-- Search --}}
        <div class="relative hidden sm:block" x-data="{ open: false }">
            <div class="relative">
                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-zinc-500" />
                <input type="text" placeholder="Buscar produtos, pedidos, NFe..."
                       class="w-64 lg:w-80 pl-10 pr-4 py-2 text-sm rounded-lg bg-gray-100 dark:bg-zinc-700/50 border-0 text-gray-900 dark:text-zinc-100 placeholder-gray-400 dark:placeholder-zinc-500 focus:ring-2 focus:ring-primary-500 focus:bg-white dark:focus:bg-zinc-700"
                       @focus="open = true" @blur="setTimeout(() => open = false, 200)">
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        {{-- Theme toggle --}}
        <button @click="darkMode = !darkMode" class="p-2 rounded-lg text-gray-500 dark:text-zinc-400 hover:bg-gray-100 dark:hover:bg-zinc-700 transition-colors" title="Alternar tema">
            <x-heroicon-o-sun x-show="darkMode" class="w-5 h-5" />
            <x-heroicon-o-moon x-show="!darkMode" class="w-5 h-5" />
        </button>

        {{-- Notifications --}}
        <button class="relative p-2 rounded-lg text-gray-500 dark:text-zinc-400 hover:bg-gray-100 dark:hover:bg-zinc-700 transition-colors" title="Notificacoes">
            <x-heroicon-o-bell class="w-5 h-5" />
            {{-- Notification dot --}}
            {{-- <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span> --}}
        </button>

        {{-- User menu --}}
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-zinc-700 transition-colors">
                <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center">
                    <span class="text-white text-sm font-medium">{{ substr(auth()->user()->name ?? 'U', 0, 1) }}</span>
                </div>
                <span class="hidden sm:block text-sm font-medium text-gray-700 dark:text-zinc-300">{{ auth()->user()->name ?? 'Usuario' }}</span>
                <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-400 hidden sm:block" />
            </button>

            <div x-show="open" @click.away="open = false" x-transition
                 class="absolute right-0 mt-2 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-gray-200 dark:border-zinc-700 py-1 z-50">
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-100 dark:hover:bg-zinc-700">
                    <x-heroicon-o-user class="w-4 h-4" />
                    Meu Perfil
                </a>
                <a href="{{ route('settings.index') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-100 dark:hover:bg-zinc-700">
                    <x-heroicon-o-cog-6-tooth class="w-4 h-4" />
                    Configuracoes
                </a>
                <hr class="my-1 border-gray-200 dark:border-zinc-700">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-zinc-700">
                        <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4" />
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
