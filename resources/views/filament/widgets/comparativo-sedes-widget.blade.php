<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Comparativo por sede</x-slot>

        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">Sede</th>
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">Monto vendido</th>
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">Producto top</th>
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">Empleado destacado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->filas as $fila)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $fila['sede']->nombre }}</td>
                            <td class="px-3 py-2 text-gray-950 dark:text-white">${{ $fila['monto_vendido'] }}</td>
                            <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $fila['producto_top'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $fila['empleado_destacado'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-gray-500">No hay sedes en el alcance actual.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
