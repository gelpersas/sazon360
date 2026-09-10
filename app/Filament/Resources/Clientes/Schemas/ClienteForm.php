<?php

namespace App\Filament\Resources\Clientes\Schemas;

use App\Enums\TipoDocumentoCliente;
use App\Enums\TipoPersona;
use App\Filament\Support\ToggleEstado;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Layout en una cuadrícula de 4 columnas (no 2) para poder poner "Número de
 * documento" y "DV" en la misma línea con anchos distintos (3/4 + 1/4) —
 * pedido explícito del usuario. Persona natural usa nombres/apellidos por
 * separado; persona jurídica usa razón social; "Nombre comercial" es válido
 * para ambas (ver el docblock de App\Models\Cliente y DEC-050).
 */
class ClienteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Toggle en vez de Select: solo hay 2 opciones excluyentes,
                // el switch cambia el resto del formulario al instante
                // (pedido explícito del usuario) — igual criterio aplicado
                // a los "estado" activo/inactivo de otros Resources, ver
                // docs/DECISIONES.md. El campo real sigue siendo el enum
                // TipoPersona (columna tipo_persona) — afterStateHydrated/
                // dehydrateStateUsing traducen entre el booleano del
                // switch y el value real que se guarda.
                Toggle::make('tipo_persona')
                    ->label(fn (Get $get) => 'Tipo de persona: '.self::tipoPersona($get('tipo_persona'))?->getLabel())
                    ->onIcon('heroicon-m-building-office-2')
                    ->offIcon('heroicon-m-user')
                    ->afterStateHydrated(fn (Toggle $component, $state) => $component->state(self::tipoPersona($state) === TipoPersona::Juridica))
                    ->dehydrateStateUsing(fn ($state) => $state ? TipoPersona::Juridica->value : TipoPersona::Natural->value)
                    ->live()
                    ->columnSpan(2)
                    ->inline(false),
                Select::make('tipo_documento')
                    ->label('Tipo de documento')
                    ->helperText('Códigos DIAN — confirmar con el contador de Dulcita si el cliente no encaja en estas opciones comunes.')
                    ->options(TipoDocumentoCliente::class)
                    ->live()
                    ->required()
                    ->native(false)
                    ->columnSpan(2),

                TextInput::make('nombres')
                    ->required(fn (Get $get) => self::esNatural($get('tipo_persona')))
                    ->visible(fn (Get $get) => self::esNatural($get('tipo_persona')))
                    ->maxLength(255)
                    ->columnSpan(2),
                TextInput::make('apellidos')
                    ->required(fn (Get $get) => self::esNatural($get('tipo_persona')))
                    ->visible(fn (Get $get) => self::esNatural($get('tipo_persona')))
                    ->maxLength(255)
                    ->columnSpan(2),

                TextInput::make('razon_social')
                    ->label('Razón social')
                    ->required(fn (Get $get) => ! self::esNatural($get('tipo_persona')))
                    ->visible(fn (Get $get) => ! self::esNatural($get('tipo_persona')))
                    ->maxLength(255)
                    ->columnSpan(4),

                TextInput::make('nombre_comercial')
                    ->label('Nombre comercial')
                    ->helperText('Opcional — el nombre con el que el cliente opera de cara al público, si es distinto del nombre/razón social (ej. una persona natural con negocio propio).')
                    ->maxLength(255)
                    ->columnSpan(4),

                TextInput::make('numero_documento')
                    ->label('Número de documento')
                    ->required()
                    ->maxLength(50)
                    ->unique(
                        table: 'clientes',
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule, Get $get) => $rule
                            ->where('empresa_id', Filament::getTenant()?->id)
                            ->where('tipo_documento', self::tipoDocumento($get('tipo_documento'))?->value),
                    )
                    ->validationMessages([
                        'unique' => 'Ya existe un cliente con ese tipo y número de documento.',
                    ])
                    ->columnSpan(3),
                TextInput::make('dv')
                    ->label('DV')
                    ->helperText('Solo NIT.')
                    ->maxLength(1)
                    ->visible(fn (Get $get) => self::tipoDocumento($get('tipo_documento')) === TipoDocumentoCliente::NIT)
                    ->columnSpan(1),

                TextInput::make('direccion')
                    ->columnSpan(2),
                TextInput::make('telefono')
                    ->tel()
                    ->maxLength(50)
                    ->columnSpan(2),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255)
                    ->columnSpan(2),
                ToggleEstado::make()->columnSpan(2),
            ])
            ->columns(4);
    }

    private static function esNatural(mixed $valor): bool
    {
        return self::tipoPersona($valor) !== TipoPersona::Juridica;
    }

    /**
     * $get('tipo_persona') puede devolver, según el momento del ciclo de
     * vida del formulario: una instancia del enum (registro ya cargado), el
     * string del value, o un booleano (el estado interno del Toggle
     * mientras se interactúa en vivo, antes de que dehydrateStateUsing lo
     * traduzca al guardar) — normaliza los tres casos en un solo lugar.
     */
    private static function tipoPersona(mixed $valor): ?TipoPersona
    {
        if ($valor instanceof TipoPersona) {
            return $valor;
        }

        if (is_bool($valor)) {
            return $valor ? TipoPersona::Juridica : TipoPersona::Natural;
        }

        return TipoPersona::tryFrom($valor ?? '');
    }

    private static function tipoDocumento(mixed $valor): ?TipoDocumentoCliente
    {
        return $valor instanceof TipoDocumentoCliente ? $valor : TipoDocumentoCliente::tryFrom($valor ?? '');
    }
}
