{{-- Desktop sidebar --}}
<aside class="sidebar fixed inset-y-0 left-0 z-40 flex-col hidden lg:flex transition-all duration-300 ease-smooth"
       :class="sidebarOpen ? 'w-[280px]' : 'w-[72px]'">

    {{-- Logo area --}}
    <div class="flex items-center h-16 px-5 border-b border-surface-600/30">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary-400 to-violet-500 flex items-center justify-center flex-shrink-0 logo-glow">
                <span class="text-white font-bold text-sm">P</span>
            </div>
            <span x-show="sidebarOpen" x-transition.opacity.duration.200ms class="text-white font-semibold text-lg truncate tracking-tight">Privus</span>
        </a>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-0.5">
        {{-- Main --}}
        <div x-show="sidebarOpen" x-transition.opacity.duration.150ms class="nav-section-title">Principal</div>

        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <x-heroicon-o-squares-2x2 class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Dashboard</span>
        </a>

        <a href="{{ route('companies.index') }}" class="sidebar-link {{ request()->routeIs('companies.*') ? 'active' : '' }}">
            <x-heroicon-o-building-office class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Empresas</span>
        </a>

        {{-- Catalog --}}
        <div x-show="sidebarOpen" x-transition.opacity.duration.150ms class="nav-section-title">Catálogo</div>

        <a href="{{ route('products.index') }}" class="sidebar-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
            <x-heroicon-o-cube class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Produtos</span>
        </a>

        <a href="{{ route('kits.index') }}" class="sidebar-link {{ request()->routeIs('kits.*') ? 'active' : '' }}">
            <x-heroicon-o-rectangle-group class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Kits</span>
        </a>

        <a href="{{ route('stock.index') }}" class="sidebar-link {{ request()->routeIs('stock.*') ? 'active' : '' }}">
            <x-heroicon-o-archive-box class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Estoque</span>
        </a>

        {{-- Sales --}}
        <div x-show="sidebarOpen" x-transition.opacity.duration.150ms class="nav-section-title">Vendas</div>

        <a href="{{ route('orders.index') }}" class="sidebar-link {{ request()->routeIs('orders.*') ? 'active' : '' }}">
            <x-heroicon-o-shopping-bag class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Pedidos</span>
        </a>

        <a href="{{ route('production.index') }}" class="sidebar-link {{ request()->routeIs('production.*') ? 'active' : '' }}">
            <x-heroicon-o-wrench-screwdriver class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Produção</span>
        </a>

        <a href="{{ route('expedition.index') }}" class="sidebar-link {{ request()->routeIs('expedition.*') ? 'active' : '' }}">
            <x-heroicon-o-truck class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Expedição</span>
        </a>

        <a href="{{ route('customers.index') }}" class="sidebar-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
            <x-heroicon-o-users class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Clientes</span>
        </a>

        {{-- Integrations --}}
        <div x-show="sidebarOpen" x-transition.opacity.duration.150ms class="nav-section-title">Integrações</div>

        <a href="{{ route('marketplaces.index') }}" class="sidebar-link {{ request()->routeIs('marketplaces.*') ? 'active' : '' }}">
            <x-heroicon-o-globe-alt class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Marketplaces</span>
        </a>

        <a href="{{ route('invoices.index') }}" class="sidebar-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
            <x-heroicon-o-document-text class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Notas Fiscais</span>
        </a>

        {{-- Intelligence --}}
        <div x-show="sidebarOpen" x-transition.opacity.duration.150ms class="nav-section-title">Inteligência</div>

        <a href="{{ route('listings.index') }}" class="sidebar-link {{ request()->routeIs('listings.*') ? 'active' : '' }}">
            <x-heroicon-o-megaphone class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Anúncios</span>
        </a>

        <a href="#" class="sidebar-link opacity-60">
            <x-heroicon-o-sparkles class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms class="flex items-center gap-2">
                IA
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-violet-500/20 text-violet-400 border border-violet-500/30">BETA</span>
            </span>
        </a>

        <a href="#" class="sidebar-link opacity-60">
            <x-heroicon-o-chart-bar class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Relatórios</span>
        </a>
    </nav>

    {{-- Sidebar footer --}}
    <div class="border-t border-surface-600/30 p-3">
        <a href="{{ route('logs.index') }}" class="sidebar-link {{ request()->routeIs('logs.*') ? 'active' : '' }}">
            <x-heroicon-o-clipboard-document-list class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Logs</span>
        </a>
        <a href="{{ route('settings.index') }}" class="sidebar-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
            <x-heroicon-o-cog-6-tooth class="icon" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Configurações</span>
        </a>

        {{-- Collapse button --}}
        <button @click="sidebarOpen = !sidebarOpen" class="sidebar-link w-full mt-1">
            <x-heroicon-o-chevron-double-left class="icon transition-transform duration-300" ::class="!sidebarOpen && 'rotate-180'" />
            <span x-show="sidebarOpen" x-transition.opacity.duration.150ms>Recolher</span>
        </button>
    </div>
</aside>

{{-- Mobile sidebar --}}
<aside x-show="mobileSidebar"
       x-transition:enter="transform transition ease-smooth duration-300"
       x-transition:enter-start="-translate-x-full"
       x-transition:enter-end="translate-x-0"
       x-transition:leave="transform transition ease-smooth duration-200"
       x-transition:leave-start="translate-x-0"
       x-transition:leave-end="-translate-x-full"
       class="sidebar fixed inset-y-0 left-0 z-40 w-[280px] flex-col lg:hidden">

    <div class="flex items-center justify-between h-16 px-5 border-b border-surface-600/30">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary-400 to-violet-500 flex items-center justify-center logo-glow">
                <span class="text-white font-bold text-sm">P</span>
            </div>
            <span class="text-white font-semibold text-lg tracking-tight">Privus</span>
        </a>
        <button @click="mobileSidebar = false" class="topbar-icon-btn">
            <x-heroicon-o-x-mark class="w-5 h-5" />
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-0.5">
        <div class="nav-section-title">Principal</div>
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <x-heroicon-o-squares-2x2 class="icon" />
            <span>Dashboard</span>
        </a>
        <a href="{{ route('companies.index') }}" class="sidebar-link {{ request()->routeIs('companies.*') ? 'active' : '' }}">
            <x-heroicon-o-building-office class="icon" />
            <span>Empresas</span>
        </a>

        <div class="nav-section-title">Catálogo</div>
        <a href="{{ route('products.index') }}" class="sidebar-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
            <x-heroicon-o-cube class="icon" />
            <span>Produtos</span>
        </a>
        <a href="{{ route('kits.index') }}" class="sidebar-link {{ request()->routeIs('kits.*') ? 'active' : '' }}">
            <x-heroicon-o-rectangle-group class="icon" />
            <span>Kits</span>
        </a>
        <a href="{{ route('stock.index') }}" class="sidebar-link {{ request()->routeIs('stock.*') ? 'active' : '' }}">
            <x-heroicon-o-archive-box class="icon" />
            <span>Estoque</span>
        </a>

        <div class="nav-section-title">Vendas</div>
        <a href="{{ route('orders.index') }}" class="sidebar-link {{ request()->routeIs('orders.*') ? 'active' : '' }}">
            <x-heroicon-o-shopping-bag class="icon" />
            <span>Pedidos</span>
        </a>
        <a href="{{ route('production.index') }}" class="sidebar-link {{ request()->routeIs('production.*') ? 'active' : '' }}">
            <x-heroicon-o-wrench-screwdriver class="icon" />
            <span>Produção</span>
        </a>
        <a href="{{ route('expedition.index') }}" class="sidebar-link {{ request()->routeIs('expedition.*') ? 'active' : '' }}">
            <x-heroicon-o-truck class="icon" />
            <span>Expedição</span>
        </a>
        <a href="{{ route('customers.index') }}" class="sidebar-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
            <x-heroicon-o-users class="icon" />
            <span>Clientes</span>
        </a>

        <div class="nav-section-title">Integrações</div>
        <a href="{{ route('marketplaces.index') }}" class="sidebar-link {{ request()->routeIs('marketplaces.*') ? 'active' : '' }}">
            <x-heroicon-o-globe-alt class="icon" />
            <span>Marketplaces</span>
        </a>
        <a href="{{ route('invoices.index') }}" class="sidebar-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
            <x-heroicon-o-document-text class="icon" />
            <span>Notas Fiscais</span>
        </a>

        <div class="nav-section-title">Inteligência</div>
        <a href="{{ route('listings.index') }}" class="sidebar-link {{ request()->routeIs('listings.*') ? 'active' : '' }}">
            <x-heroicon-o-megaphone class="icon" />
            <span>Anúncios</span>
        </a>

        <div class="nav-section-title">Sistema</div>
        <a href="{{ route('logs.index') }}" class="sidebar-link {{ request()->routeIs('logs.*') ? 'active' : '' }}">
            <x-heroicon-o-clipboard-document-list class="icon" />
            <span>Logs</span>
        </a>
        <a href="{{ route('settings.index') }}" class="sidebar-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
            <x-heroicon-o-cog-6-tooth class="icon" />
            <span>Configurações</span>
        </a>
    </nav>
</aside>
