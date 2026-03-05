<header class="topbar h-16 px-6 flex items-center justify-between">
    <div class="flex items-center gap-4">
        {{-- Mobile menu toggle --}}
        <button @click="mobileSidebar = true" class="lg:hidden topbar-icon-btn" title="Menu">
            <x-heroicon-o-bars-3 class="w-5 h-5" />
        </button>

        {{-- Search --}}
        <div class="relative hidden sm:block" x-data="{ open: false, search: '' }" @click.away="open = false">
            <div class="topbar-search min-w-[320px] lg:min-w-[380px]">
                <x-heroicon-o-magnifying-glass class="w-4 h-4 text-surface-500 flex-shrink-0" />
                <input type="text"
                       x-model="search"
                       placeholder="Buscar produtos, pedidos, NFe..."
                       class="bg-transparent border-0 text-sm text-surface-100 placeholder-surface-500 focus:outline-none focus:ring-0 w-full"
                       @focus="open = true">
                <kbd class="hidden lg:flex items-center gap-1 px-2 py-0.5 text-[10px] font-mono text-surface-500 bg-surface-700/50 rounded border border-surface-600/50">
                    <span>⌘</span><span>K</span>
                </kbd>
            </div>

            {{-- Search Dropdown --}}
            <div x-show="open && search.length > 0"
                 x-transition:enter="transition ease-smooth duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-smooth duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-2"
                 class="absolute top-full left-0 right-0 mt-2 py-2 bg-surface-800/95 backdrop-blur-xl border border-surface-600/50 rounded-xl shadow-lg z-50">
                <div class="px-4 py-2 text-xs text-surface-500 font-medium">Sugestões de busca</div>
                <a href="#" class="dropdown-item">
                    <x-heroicon-o-cube class="w-4 h-4" />
                    <span>Produtos recentes</span>
                </a>
                <a href="#" class="dropdown-item">
                    <x-heroicon-o-shopping-bag class="w-4 h-4" />
                    <span>Pedidos do dia</span>
                </a>
                <a href="#" class="dropdown-item">
                    <x-heroicon-o-document-text class="w-4 h-4" />
                    <span>Notas fiscais pendentes</span>
                </a>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-2">
        {{-- Theme toggle --}}
        <button class="topbar-icon-btn" title="Alternar tema">
            <x-heroicon-o-moon class="w-5 h-5" />
        </button>

        {{-- Notifications --}}
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="topbar-icon-btn relative" title="Notificações">
                <x-heroicon-o-bell class="w-5 h-5" />
                {{-- Notification badge --}}
                <span class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-primary-500 text-[10px] font-bold text-white rounded-full flex items-center justify-center border-2 border-surface-950">
                    3
                </span>
            </button>

            {{-- Notifications dropdown --}}
            <div x-show="open" @click.away="open = false"
                 x-transition:enter="transition ease-smooth duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-smooth duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-2"
                 class="absolute right-0 top-full mt-2 w-80 bg-surface-800/95 backdrop-blur-xl border border-surface-600/50 rounded-xl py-2 z-50 shadow-lg">
                <div class="px-4 py-2 border-b border-surface-600/30 flex items-center justify-between">
                    <span class="text-sm font-semibold text-surface-200">Notificações</span>
                    <button class="text-xs text-primary-400 hover:text-primary-300">Marcar todas como lidas</button>
                </div>
                <div class="max-h-80 overflow-y-auto">
                    <a href="#" class="flex items-start gap-3 px-4 py-3 hover:bg-white/5 transition-colors border-l-2 border-primary-500">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center flex-shrink-0">
                            <x-heroicon-o-check class="w-4 h-4 text-emerald-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-surface-200 font-medium">Pedido #1234 confirmado</p>
                            <p class="text-xs text-surface-500 mt-0.5">Mercado Livre • há 5 minutos</p>
                        </div>
                    </a>
                    <a href="#" class="flex items-start gap-3 px-4 py-3 hover:bg-white/5 transition-colors border-l-2 border-amber-500">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center flex-shrink-0">
                            <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-surface-200 font-medium">Estoque baixo: Produto XYZ</p>
                            <p class="text-xs text-surface-500 mt-0.5">Apenas 3 unidades restantes</p>
                        </div>
                    </a>
                    <a href="#" class="flex items-start gap-3 px-4 py-3 hover:bg-white/5 transition-colors border-l-2 border-surface-600">
                        <div class="w-8 h-8 rounded-lg bg-surface-700 flex items-center justify-center flex-shrink-0">
                            <x-heroicon-o-document-text class="w-4 h-4 text-surface-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-surface-400">NF-e #000123 autorizada</p>
                            <p class="text-xs text-surface-600 mt-0.5">há 2 horas</p>
                        </div>
                    </a>
                </div>
                <div class="px-4 py-2 border-t border-surface-600/30">
                    <a href="#" class="text-xs text-primary-400 hover:text-primary-300 font-medium flex items-center justify-center gap-1">
                        Ver todas
                        <x-heroicon-o-arrow-right class="w-3 h-3" />
                    </a>
                </div>
            </div>
        </div>

        {{-- Quick actions --}}
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="topbar-icon-btn" title="Ações rápidas">
                <x-heroicon-o-plus class="w-5 h-5" />
            </button>

            {{-- Quick actions dropdown --}}
            <div x-show="open" @click.away="open = false"
                 x-transition:enter="transition ease-smooth duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-smooth duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-2"
                 class="absolute right-0 top-full mt-2 w-56 bg-surface-800/95 backdrop-blur-xl border border-surface-600/50 rounded-xl py-2 z-50 shadow-lg">
                <div class="px-3 py-1.5 text-xs text-surface-500 font-medium uppercase tracking-wider">Ações rápidas</div>
                <a href="{{ route('products.create') }}" class="dropdown-item">
                    <x-heroicon-o-cube class="w-4 h-4" />
                    <span>Novo Produto</span>
                </a>
                <a href="{{ route('orders.create') }}" class="dropdown-item">
                    <x-heroicon-o-shopping-bag class="w-4 h-4" />
                    <span>Novo Pedido</span>
                </a>
                <a href="{{ route('invoices.create') }}" class="dropdown-item">
                    <x-heroicon-o-document-text class="w-4 h-4" />
                    <span>Nova Nota Fiscal</span>
                </a>
                <div class="my-1 border-t border-surface-600/30"></div>
                <a href="{{ route('marketplaces.sync') }}" class="dropdown-item">
                    <x-heroicon-o-arrow-path class="w-4 h-4" />
                    <span>Sincronizar ML</span>
                </a>
            </div>
        </div>

        <div class="w-px h-8 bg-surface-700/50 mx-1"></div>

        {{-- User menu --}}
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex items-center gap-3 p-1 rounded-xl hover:bg-white/5 transition-colors">
                <div class="user-avatar">
                    {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                </div>
                <div class="hidden md:block text-left">
                    <div class="text-sm font-medium text-surface-200">{{ auth()->user()->name ?? 'Usuário' }}</div>
                    <div class="text-xs text-surface-500">{{ auth()->user()->email ?? '' }}</div>
                </div>
                <x-heroicon-o-chevron-down class="w-4 h-4 text-surface-500 hidden md:block" />
            </button>

            {{-- User dropdown --}}
            <div x-show="open" @click.away="open = false"
                 x-transition:enter="transition ease-smooth duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-smooth duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-2"
                 class="absolute right-0 top-full mt-2 w-56 bg-surface-800/95 backdrop-blur-xl border border-surface-600/50 rounded-xl py-2 z-50 shadow-lg">
                <div class="px-4 py-3 border-b border-surface-600/30">
                    <div class="text-sm font-semibold text-surface-200">{{ auth()->user()->name ?? 'Usuário' }}</div>
                    <div class="text-xs text-surface-500 truncate">{{ auth()->user()->email ?? '' }}</div>
                </div>
                <a href="{{ route('profile.edit') }}" class="dropdown-item">
                    <x-heroicon-o-user class="w-4 h-4" />
                    <span>Meu Perfil</span>
                </a>
                <a href="{{ route('settings.index') }}" class="dropdown-item">
                    <x-heroicon-o-cog-6-tooth class="w-4 h-4" />
                    <span>Configurações</span>
                </a>
                <a href="#" class="dropdown-item">
                    <x-heroicon-o-question-mark-circle class="w-4 h-4" />
                    <span>Ajuda & Suporte</span>
                </a>
                <div class="my-1 border-t border-surface-600/30"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item w-full text-left text-red-400 hover:text-red-300 hover:bg-red-500/10">
                        <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4" />
                        <span>Sair</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
