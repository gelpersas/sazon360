<?php

namespace App\Filament\Pages\Tenancy;

use App\Enums\EstadoEmpresa;
use App\Models\Empresa;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;

class EditEmpresaProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Perfil de la empresa';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre (identificador interno)')
                    ->required()
                    ->maxLength(255),
                TextInput::make('nombre_comercial')
                    ->label('Nombre comercial (para recibos)')
                    ->maxLength(255),

                TextInput::make('nit')
                    ->label('NIT')
                    ->maxLength(20)
                    // El NIT queda fijo una vez creado (ver Empresa::booted())
                    // — deshabilitado en el formulario apenas ya tiene valor;
                    // un campo disabled no se envía al guardar, así que ni
                    // siquiera llega al modelo un intento de cambiarlo desde
                    // aquí (el guardián real, de todos modos, está en el
                    // modelo, no solo en el formulario).
                    ->disabled(fn (?Empresa $record) => filled($record?->nit)),
                TextInput::make('dv')
                    ->label('DV')
                    ->maxLength(1)
                    ->numeric()
                    ->disabled(fn (?Empresa $record) => filled($record?->nit)),
                TextInput::make('regimen_tributario')
                    ->label('Régimen tributario')
                    ->maxLength(255),
                TextInput::make('actividad_economica_ciiu')
                    ->label('Actividad económica (CIIU)')
                    ->maxLength(10),

                TextInput::make('direccion')
                    ->maxLength(255),
                TextInput::make('telefono')
                    ->tel()
                    ->maxLength(50),
                TextInput::make('whatsapp')
                    ->tel()
                    ->maxLength(50),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),

                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->disk('public')
                    ->directory('logos-empresas')
                    ->maxSize(2048),

                Select::make('estado')
                    ->options(EstadoEmpresa::class)
                    ->required(),
                Textarea::make('notas_internas')
                    ->label('Notas internas (solo soporte)')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }
}
