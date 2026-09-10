<?php

namespace App\Filament\Exports;

use App\Models\Pedido;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

/**
 * Exportación Excel/CSV del historial de ventas (ver docs/DECISIONES.md
 * DEC-067) — reutiliza el mecanismo nativo de Filament (openspout ya estaba
 * instalado como dependencia interna de Filament, sin usarse todavía). La
 * consulta que exporta ya viene acotada por VentaResource::getEloquentQuery()
 * (tenant + sede) y respeta los filtros/búsqueda aplicados en la tabla —
 * ver Filament\Actions\Concerns\CanExportRecords::setUp().
 */
class VentaExporter extends Exporter
{
    protected static ?string $model = Pedido::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('sede.nombre')->label('Sede'),
            ExportColumn::make('tipo')->label('Tipo')->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('mesa.nombre')->label('Mesa'),
            ExportColumn::make('cliente.nombre')->label('Cliente'),
            ExportColumn::make('usuario.name')->label('Atendido por'),
            ExportColumn::make('estado')->label('Estado')->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('total')->label('Total')->state(fn (Pedido $record) => $record->total()),
            ExportColumn::make('created_at')->label('Creado')->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i')),
            ExportColumn::make('cerrado_at')->label('Cerrado')->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Tu exportación de ventas ha finalizado y '.Number::format($export->successful_rows).' '.str('fila')->plural($export->successful_rows).' se exportaron correctamente.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('fila')->plural($failedRowsCount).' no se pudieron exportar.';
        }

        return $body;
    }
}
