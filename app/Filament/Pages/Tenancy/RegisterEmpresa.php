<?php

namespace App\Filament\Pages\Tenancy;

use App\Enums\Rol;
use App\Models\Empresa;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RegisterEmpresa extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Registrar empresa';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    protected function handleRegistration(array $data): Model
    {
        $data['slug'] = $this->slugUnico($data['nombre']);
        $data['estado'] = 'activa';

        /** @var Empresa $empresa */
        $empresa = Empresa::create($data);

        $empresa->accesos()->create([
            'user_id' => Filament::auth()->id(),
            'rol' => Rol::AdministracionCentral,
        ]);

        return $empresa;
    }

    protected function slugUnico(string $nombre): string
    {
        $base = Str::slug($nombre);
        $slug = $base;
        $sufijo = 1;

        while (Empresa::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$sufijo}";
            $sufijo++;
        }

        return $slug;
    }
}
