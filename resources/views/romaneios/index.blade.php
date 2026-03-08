<x-app-layout>
    <x-slot name="header">Romaneios</x-slot>
    <x-slot name="subtitle">Histórico de romaneios de despacho</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('expedition.index') }}" class="hover:underline">Expedição</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span>Romaneios</span>
        </li>
    </x-slot>

    <x-ui.card :padding="false">
        @if($romaneios->isEmpty())
            <x-ui.empty-state
                title="Nenhum romaneio criado"
                description="Os romaneios criados na página de expedição aparecerão aqui.">
                <x-slot name="icon">
                    <x-heroicon-o-clipboard-document-list class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
            </x-ui.empty-state>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Romaneio</th>
                        <th>Pedidos</th>
                        <th>Volumes</th>
                        <th>Status</th>
                        <th>Criado por</th>
                        <th>Data</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($romaneios as $romaneio)
                    <tr wire:key="rom-{{ $romaneio->id }}">
                        <td>
                            <a href="{{ route('romaneios.show', $romaneio) }}"
                               class="font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                                {{ $romaneio->name }}
                            </a>
                        </td>
                        <td>{{ $romaneio->total_orders }}</td>
                        <td>{{ $romaneio->total_volumes_scanned }}/{{ $romaneio->total_volumes }}</td>
                        <td>
                            <x-ui.badge :color="$romaneio->isOpen() ? 'warning' : 'success'">
                                {{ $romaneio->isOpen() ? 'Aberto' : 'Fechado' }}
                            </x-ui.badge>
                        </td>
                        <td class="text-sm text-gray-600 dark:text-zinc-400">
                            {{ $romaneio->createdBy?->name ?? '—' }}
                        </td>
                        <td class="text-sm text-gray-500 dark:text-zinc-400">
                            {{ $romaneio->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="text-right">
                            <a href="{{ route('romaneios.show', $romaneio) }}" class="btn-ghost btn-xs">
                                <x-heroicon-o-eye class="w-4 h-4" />
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if($romaneios->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $romaneios->links() }}
            </div>
            @endif
        @endif
    </x-ui.card>
</x-app-layout>
