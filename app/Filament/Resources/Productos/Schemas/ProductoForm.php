<?php

namespace App\Filament\Resources\Productos\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('categoria_id')
                    ->relationship('categoria', 'nombre')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255)
                    ->scopedUnique(),
                Textarea::make('descripcion')
                    ->helperText('Detalle visible para el cliente/mesero al tocar el producto en el POS (ingredientes, tamaño, etc.).')
                    ->maxLength(1000)
                    ->columnSpanFull(),
                // Mismo patrón que el logo de la empresa (ver DEC-035:
                // FileUpload, disco "public", carpeta propia).
                FileUpload::make('imagen_path')
                    ->label('Imagen de referencia')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('imagenes-productos')
                    ->maxSize(2048)
                    ->columnSpanFull(),
                TextInput::make('precio')
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0)
                    ->required(),
                // Radio en vez de Select: 3 opciones excluyentes, todas
                // visibles a la vez es más rápido de leer/tocar en el POS
                // táctil que abrir un desplegable — mismo criterio que el
                // switch de "estado" activo/inactivo en otros Resources
                // (ver docs/DECISIONES.md).
                Radio::make('estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                        'agotado' => 'Agotado',
                    ])
                    ->default('activo')
                    ->inline()
                    ->required(),
                Radio::make('codigo_impuesto_dian')
                    ->label('Impuesto DIAN')
                    ->helperText('Requerido para poder facturar este producto electrónicamente (ver Facturación electrónica). Confirmar con el contador de Dulcita cuál aplica.')
                    ->options([
                        '01' => 'IVA',
                        '04' => 'INC (Impuesto Nacional al Consumo)',
                        'ZZ' => 'Excluido / no aplica',
                    ])
                    ->inline(),
                TextInput::make('tasa_iva')
                    ->label('Tasa (%)')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->helperText('Ej. 19.00 para IVA general, 8.00 para INC, 0.00 si está excluido.'),
            ]);
    }
}
