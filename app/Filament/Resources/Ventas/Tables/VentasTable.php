<?php

namespace App\Filament\Resources\Ventas\Tables;

use App\Enums\EstadoPedido;
use App\Enums\MedioPago;
use App\Filament\Exports\VentaExporter;
use App\Models\Pedido;
use App\Support\ReciboPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class VentasTable
{
    /**
     * Historial de ventas — sin EditAction/DeleteAction/CreateAction a
     * propósito, ver VentaResource. "Exportar" (Excel/CSV, nativo de
     * Filament) y "Exportar PDF" (custom, dompdf) respetan los filtros y la
     * búsqueda aplicados — ambos parten de la misma consulta ya acotada por
     * VentaResource::getEloquentQuery() (tenant + sede).
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('sede.nombre')->label('Sede')->searchable()->sortable(),
                TextColumn::make('tipo')->label('Tipo')->badge(),
                TextColumn::make('mesa.nombre')->label('Mesa')->placeholder('—')->searchable(),
                TextColumn::make('cliente.nombre')->label('Cliente')->placeholder('—')->searchable(),
                TextColumn::make('usuario.name')->label('Atendido por')->searchable(),
                TextColumn::make('estado')->label('Estado')->badge(),
                TextColumn::make('total')
                    ->label('Total')
                    ->state(fn (Pedido $record) => $record->total())
                    ->money('usd'),
                TextColumn::make('created_at')->label('Creado')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('cerrado_at')->label('Cerrado')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
            ])
            ->filters([
                Filter::make('rango')
                    ->schema([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $desde) => $q->whereDate('created_at', '>=', $desde))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $hasta) => $q->whereDate('created_at', '<=', $hasta));
                    }),
                SelectFilter::make('sede_id')
                    ->label('Sede')
                    ->options(function () {
                        $empresa = Filament::getTenant();

                        if (! $empresa) {
                            return [];
                        }

                        return Auth::user()->sedesAccesibles($empresa)->pluck('nombre', 'id');
                    }),
                SelectFilter::make('estado')
                    ->options(EstadoPedido::class),
                SelectFilter::make('medio_pago')
                    ->label('Medio de pago')
                    ->options(MedioPago::class)
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $medio) => $q->whereHas('pagos', fn (Builder $q) => $q->where('medio', $medio)),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('recibo')
                    ->label('Recibo')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->action(fn (Pedido $record) => ReciboPdf::generar($record)),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar Excel/CSV')
                    ->exporter(VentaExporter::class),
                Action::make('exportarPdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    // Pdf::download() devuelve un Response plano, que
                    // Livewire descarta en silencio (solo dispara la
                    // descarga si es StreamedResponse/BinaryFileResponse —
                    // ver App\Support\ReciboPdf), de ahí el streamDownload().
                    ->action(function (HasTable $livewire) {
                        $ventas = $livewire->getTableQueryForExport()
                            ->with(['sede', 'mesa', 'cliente', 'usuario'])
                            ->get();

                        $totalConsolidado = $ventas->reduce(
                            fn (string $acumulado, Pedido $venta) => bcadd($acumulado, $venta->total(), 2),
                            '0.00',
                        );

                        $pdf = Pdf::loadView('pdf.ventas-listado', [
                            'ventas' => $ventas,
                            'totalConsolidado' => $totalConsolidado,
                            'empresa' => Filament::getTenant(),
                        ])->output();

                        return response()->streamDownload(
                            fn () => print ($pdf),
                            'ventas.pdf',
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),
            ]);
    }
}
