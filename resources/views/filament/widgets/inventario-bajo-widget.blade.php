<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Inventario bajo</x-slot>

        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($this->alertas as $alerta)
                <li class="flex items-center justify-between py-2 text-sm">
                    <div>
                        <span class="text-gray-950 dark:text-white">{{ $alerta['insumo'] }}</span>
                        <span class="text-gray-500 dark:text-gray-400">· {{ $alerta['sede'] }}</span>
                    </div>
                    <span class="font-medium text-warning-600 dark:text-warning-400">
                        {{ $alerta['cantidad_actual'] }} / mín. {{ $alerta['stock_minimo'] }}
                    </span>
                </li>
            @empty
                <li class="py-4 text-center text-sm text-gray-500">Sin alertas de inventario en el alcance actual.</li>
            @endforelse
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
