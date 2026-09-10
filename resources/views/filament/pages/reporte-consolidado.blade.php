<x-filament-panels::page>
    <form wire:submit="actualizar" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-700 dark:text-gray-300">Desde</label>
            <input type="date" wire:model="desde" class="fi-input mt-1 block rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
        </div>
        <div>
            <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-700 dark:text-gray-300">Hasta</label>
            <input type="date" wire:model="hasta" class="fi-input mt-1 block rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
        </div>
        <x-filament::button type="submit">
            Actualizar
        </x-filament::button>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Ventas por sede ({{ $desde }} — {{ $hasta }})</x-slot>

        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">Sede</th>
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">Pedidos cobrados</th>
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">Total vendido</th>
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">Ticket promedio</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->filas as $fila)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $fila['sede']->nombre }}</td>
                            <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $fila['pedidos_cobrados'] }}</td>
                            <td class="px-3 py-2 text-gray-950 dark:text-white">${{ $fila['total_vendido'] }}</td>
                            <td class="px-3 py-2 text-gray-950 dark:text-white">${{ $fila['ticket_promedio'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-gray-500">Esta empresa todavía no tiene sedes.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($this->filas->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 font-semibold dark:border-gray-600">
                            <td class="px-3 py-2 text-gray-950 dark:text-white">Consolidado ({{ $this->filas->count() }} sede{{ $this->filas->count() === 1 ? '' : 's' }})</td>
                            <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $this->pedidosConsolidados }}</td>
                            <td class="px-3 py-2 text-gray-950 dark:text-white">${{ $this->totalConsolidado }}</td>
                            <td class="px-3 py-2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
