<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>
    <x-slot name="subtitle">Visao geral do seu negocio</x-slot>

    {{-- Stats row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card title="Vendas Hoje" value="R$ 0,00" change="+0%" changeType="up">
            <x-slot name="icon">
                <x-heroicon-s-currency-dollar class="w-6 h-6 text-primary-600 dark:text-primary-400" />
            </x-slot>
        </x-ui.stat-card>

        <x-ui.stat-card title="Pedidos Pendentes" value="0">
            <x-slot name="icon">
                <x-heroicon-s-shopping-bag class="w-6 h-6 text-amber-600 dark:text-amber-400" />
            </x-slot>
        </x-ui.stat-card>

        <x-ui.stat-card title="Produtos Ativos" value="0">
            <x-slot name="icon">
                <x-heroicon-s-cube class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
            </x-slot>
        </x-ui.stat-card>

        <x-ui.stat-card title="Estoque Baixo" value="0">
            <x-slot name="icon">
                <x-heroicon-s-exclamation-triangle class="w-6 h-6 text-red-600 dark:text-red-400" />
            </x-slot>
        </x-ui.stat-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Sales chart --}}
        <div class="lg:col-span-2">
            <x-ui.card title="Vendas (ultimos 30 dias)">
                <div id="sales-chart" class="h-72"></div>
            </x-ui.card>
        </div>

        {{-- Recent activity --}}
        <x-ui.card title="Atividade Recente">
            <x-ui.empty-state title="Nenhuma atividade" description="As atividades do sistema aparecerão aqui.">
                <x-slot name="icon">
                    <x-heroicon-o-clock class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
            </x-ui.empty-state>
        </x-ui.card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        {{-- Marketplaces connected --}}
        <x-ui.card title="Marketplaces Conectados">
            <x-ui.empty-state title="Nenhum marketplace" description="Conecte suas contas de marketplace em Configuracoes.">
                <x-slot name="icon">
                    <x-heroicon-o-globe-alt class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
                <x-slot name="action">
                    <a href="#" class="btn-primary">Conectar Marketplace</a>
                </x-slot>
            </x-ui.empty-state>
        </x-ui.card>

        {{-- Recent orders --}}
        <x-ui.card title="Ultimos Pedidos">
            <x-ui.empty-state title="Nenhum pedido" description="Quando voce receber pedidos, eles aparecerão aqui.">
                <x-slot name="icon">
                    <x-heroicon-o-shopping-bag class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
            </x-ui.empty-state>
        </x-ui.card>
    </div>

    @push('scripts')
    <script type="module">
        import ApexCharts from 'apexcharts';

        const isDark = document.documentElement.classList.contains('dark');

        const options = {
            chart: {
                type: 'area',
                height: 288,
                toolbar: { show: false },
                background: 'transparent',
                fontFamily: 'Inter, sans-serif',
            },
            theme: { mode: isDark ? 'dark' : 'light' },
            colors: ['#6366f1'],
            series: [{
                name: 'Vendas',
                data: Array.from({ length: 30 }, () => Math.floor(Math.random() * 5000))
            }],
            xaxis: {
                categories: Array.from({ length: 30 }, (_, i) => `${i + 1}`),
                labels: { style: { colors: isDark ? '#a1a1aa' : '#6b7280' } },
            },
            yaxis: {
                labels: {
                    formatter: (val) => `R$ ${val.toLocaleString('pt-BR')}`,
                    style: { colors: isDark ? '#a1a1aa' : '#6b7280' },
                },
            },
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'gradient',
                gradient: { opacityFrom: 0.4, opacityTo: 0.05 },
            },
            dataLabels: { enabled: false },
            grid: {
                borderColor: isDark ? '#3f3f46' : '#e5e7eb',
                strokeDashArray: 4,
            },
            tooltip: {
                y: { formatter: (val) => `R$ ${val.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` },
            },
        };

        const chart = new ApexCharts(document.querySelector('#sales-chart'), options);
        chart.render();
    </script>
    @endpush
</x-app-layout>
