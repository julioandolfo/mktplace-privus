{{-- Desktop sidebar --}}
<aside class="fixed inset-y-0 left-0 z-40 flex flex-col bg-zinc-900 dark:bg-zinc-950 border-r border-zinc-800 transition-all duration-200 hidden lg:flex"
       :class="sidebarOpen ? 'w-64' : 'w-[4.5rem]'">

    {{-- Logo area --}}
    <div class="flex items-center h-16 px-4 border-b border-zinc-800">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0">
            <div class="w-8 h-8 rounded-lg bg-primary-600 flex items-center justify-center flex-shrink-0">
                <span class="text-white font-bold text-sm">MP</span>
            </div>
            <span x-show="sidebarOpen" x-transition.opacity class="text-white font-semibold text-lg truncate">Privus</span>
        </a>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        {{-- Main --}}
        <div x-show="sidebarOpen" class="px-3 mb-2 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Principal</div>

        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <x-heroicon-o-squares-2x2 class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>Dashboard</span>
        </a>

        <a href="{{ route('companies.index') }}" class="sidebar-link {{ request()->routeIs('companies.*') ? 'active' : '' }}">
            <x-heroicon-o-building-office class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>Empresas</span>
        </a>

        {{-- Catalog --}}
        <div x-show="sidebarOpen" class="px-3 mt-6 mb-2 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Catalogo</div>

        <a href="{{ route('products.index') }}" class="sidebar-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
            <x-heroicon-o-cube class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>Produtos</span>
        </a>

        <a href="{{ route('kits.index') }}" class="sidebar-link {{ request()->routeIs('kits.*') ? 'active' : '' }}">
            <x-heroicon-o-rectangle-group class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>Kits</span>
        </a>

        <a href="{{ route('stock.index') }}" class="sidebar-link {{ request()->routeIs('stock.*') ? 'active' : '' }}">
            <x-heroicon-o-archive-box class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>Estoque</span>
        </a>

        {{-- Sales --}}
        <div x-show="sidebarOpen" class="px-3 mt-6 mb-2 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Vendas</div>

        <a href="{{ route('orders.index') }}" class="sidebar-link {{ request()->routeIs('orders.*') ? 'active' : '' }}">
            <x-heroicon-o-shopping-bag class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>Pedidos</span>
        </a>

        <a href="#" class="sidebar-link">
            <x-heroicon-o-wrench-screwdriver class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>Producao</span>
        </a>

        <a href="#" class="sidebar-link">
            <x-heroicon-o-truck class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>Expedicao</span>
        </a>

        {{-- Integrations --}}
        <div x-show="sidebarOpen" class="px-3 mt-6 mb-2 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Integracoes</div>

        <a href="#" class="sidebar-link">
            <x-heroicon-o-globe-alt class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>Marketplaces</span>
        </a>

        <a href="#" class="sidebar-link">
            <x-heroicon-o-document-text class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>Notas Fiscais</span>
        </a>

        {{-- Intelligence --}}
        <div x-show="sidebarOpen" class="px-3 mt-6 mb-2 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Inteligencia</div>

        <a href="#" class="sidebar-link">
            <x-heroicon-o-megaphone class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>Anuncios</span>
        </a>

        <a href="#" class="sidebar-link">
            <x-heroicon-o-sparkles class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>IA</span>
        </a>

        <a href="#" class="sidebar-link">
            <x-heroicon-o-chart-bar class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>Relatorios</span>
        </a>
    </nav>

    {{-- Sidebar footer --}}
    <div class="border-t border-zinc-800 p-3">
        <a href="{{ route('settings.index') }}" class="sidebar-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
            <x-heroicon-o-cog-6-tooth class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity>Configuracoes</span>
        </a>

        {{-- Collapse button --}}
        <button @click="sidebarOpen = !sidebarOpen" class="sidebar-link w-full mt-1">
            <x-heroicon-o-chevron-double-left class="icon transition-transform" ::class="!sidebarOpen && 'rotate-180'" />
            <span x-show="sidebarOpen" x-transition.opacity>Recolher</span>
        </button>
    </div>
</aside>

{{-- Mobile sidebar --}}
<aside x-show="mobileSidebar" x-transition:enter="transform transition-transform duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transform transition-transform duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
       class="fixed inset-y-0 left-0 z-40 w-64 flex flex-col bg-zinc-900 dark:bg-zinc-950 border-r border-zinc-800 lg:hidden">

    <div class="flex items-center justify-between h-16 px-4 border-b border-zinc-800">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-primary-600 flex items-center justify-center">
                <span class="text-white font-bold text-sm">MP</span>
            </div>
            <span class="text-white font-semibold text-lg">Privus</span>
        </a>
        <button @click="mobileSidebar = false" class="text-zinc-400 hover:text-white">
            <x-heroicon-o-x-mark class="w-5 h-5" />
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <x-heroicon-o-squares-2x2 class="icon" />
            <span>Dashboard</span>
        </a>
        <a href="{{ route('companies.index') }}" class="sidebar-link {{ request()->routeIs('companies.*') ? 'active' : '' }}">
            <x-heroicon-o-building-office class="icon" />
            <span>Empresas</span>
        </a>
        <a href="{{ route('products.index') }}" class="sidebar-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
            <x-heroicon-o-cube class="icon" />
            <span>Produtos</span>
        </a>
        <a href="{{ route('orders.index') }}" class="sidebar-link {{ request()->routeIs('orders.*') ? 'active' : '' }}">
            <x-heroicon-o-shopping-bag class="icon" />
            <span>Pedidos</span>
        </a>
        <a href="#" class="sidebar-link">
            <x-heroicon-o-truck class="icon" />
            <span>Expedicao</span>
        </a>
        <a href="#" class="sidebar-link">
            <x-heroicon-o-globe-alt class="icon" />
            <span>Marketplaces</span>
        </a>
        <a href="{{ route('settings.index') }}" class="sidebar-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
            <x-heroicon-o-cog-6-tooth class="icon" />
            <span>Configuracoes</span>
        </a>
    </nav>
</aside>
