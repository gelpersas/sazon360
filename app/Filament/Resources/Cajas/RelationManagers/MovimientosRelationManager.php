<?php

namespace App\Filament\Resources\Cajas\RelationManagers;

use App\Enums\TipoMovimientoCaja;
use App\Models\Caja;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Sin EditAction/DeleteAction/DissociateAction a propósito: un movimiento de
 * caja es historial de auditoría, no se corrige ni se borra (ver
 * docs/REGLAS-NEGOCIO.md, "Caja"). Si se registró mal, se compensa con otro
 * movimiento — igual que una comanda se anula, no se elimina.
 */
class MovimientosRelationManager extends RelationManager
{
    protected static string $relationship = 'movimientos';

    protected static ?string $title = 'Movimientos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tipo')
                    ->options(TipoMovimientoCaja::class)
                    ->required(),
                TextInput::make('monto')
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0.01)
                    ->required(),
                Textarea::make('descripcion')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('descripcion')
            ->columns([
                TextColumn::make('tipo')
                    ->badge(),
                TextColumn::make('monto')->money('usd'),
                TextColumn::make('descripcion'),
                TextColumn::make('usuario.name')->label('Registrado por'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar movimiento')
                    ->visible(fn (): bool => $this->getOwnerRecord()->estaAbierta())
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var Caja $caja */
                        $caja = $this->getOwnerRecord();

                        $data['empresa_id'] = $caja->empresa_id;
                        $data['usuario_id'] = Auth::id();

                        return $data;
                    }),
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
