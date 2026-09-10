<?php

namespace App\Filament\Resources\Compras\Schemas;

use App\Models\Insumo;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Solo se usa para "Registrar compra" (CreateCompra) — no hay Edit: una
 * compra ya registrada aumentó inventario, corregirla requeriría revertir
 * esos movimientos, fuera de alcance de Fase 7 (ver docs/DECISIONES.md).
 */
class CompraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sede_id')
                    ->label('Sede que recibe')
                    ->options(function () {
                        $empresa = Filament::getTenant();

                        return $empresa ? Auth::user()->sedesAccesibles($empresa)->pluck('nombre', 'id') : [];
                    })
                    ->required()
                    ->native(false),
                Select::make('proveedor_id')
                    ->label('Proveedor')
                    ->relationship('proveedor', 'nombre')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('numero_factura_proveedor')
                    ->label('N.º de factura del proveedor')
                    ->maxLength(255),
                Textarea::make('notas')
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Repeater::make('items')
                    ->label('Ítems comprados')
                    ->schema([
                        Select::make('insumo_id')
                            ->label('Insumo')
                            ->options(fn () => Insumo::query()->where('empresa_id', Filament::getTenant()?->id)->pluck('nombre', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('cantidad')
                            ->numeric()
                            ->minValue(0.001)
                            ->step(0.001)
                            ->required(),
                        TextInput::make('costo_unitario')
                            ->label('Costo unitario')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(3)
                    ->minItems(1)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
