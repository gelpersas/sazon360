<?php

namespace App\Filament\Resources\Accesos\Schemas;

use App\Enums\Rol;
use App\Models\Acceso;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Un `Acceso` en base de datos sigue siendo una fila por rol (sin cambiar
 * el esquema — ver DEC-048), pero el formulario deja elegir varios roles a
 * la vez para un mismo usuario+sede en un solo modal, tanto al crear como
 * al editar (antes había que repetir todo el modal por cada rol, ver
 * docs/DECISIONES.md). La reconciliación (crear/borrar las filas
 * `Acceso` según los roles marcados) vive en `Pages/ListAccesos.php` y
 * `Tables/AccesosTable.php` (`->using()`), no acá — este archivo solo
 * define el schema del formulario.
 */
class AccesoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'name')
                    ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->name} ({$record->email})")
                    ->searchable(['name', 'email'])
                    ->preload()
                    ->required()
                    ->live()
                    // Crear un usuario nuevo sin salir de este formulario —
                    // hoy es la única forma de dar de alta a un mesero/cajero,
                    // antes solo existía vía tinker/seeders (ver DEC-037).
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(User::class),
                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8),
                    ]),

                CheckboxList::make('roles')
                    ->label('Roles')
                    ->helperText('Marca todos los que apliquen (ej. cajero y mesero a la vez) — se guardan juntos en un solo paso.')
                    ->options(function () {
                        $empresa = Filament::getTenant();
                        $user = Auth::user();

                        $opciones = collect(Rol::cases());

                        // Solo administración central puede otorgar el rol de
                        // administración central — evita que un admin de sede
                        // se autoasigne (o le asigne a alguien) el control de
                        // toda la empresa. Reforzado también en AccesoPolicy y
                        // en Acceso::booted() (defensa en profundidad).
                        if (! $empresa || ! $user->esAdminCentralDe($empresa)) {
                            $opciones = $opciones->reject(fn (Rol $rol) => $rol === Rol::AdministracionCentral);
                        }

                        return $opciones->mapWithKeys(fn (Rol $rol) => [$rol->value => $rol->label()]);
                    })
                    ->columns(2)
                    ->live()
                    ->required()
                    ->rule(fn (Get $get, ?Acceso $record): Closure => self::reglaRoles($get, $record))
                    ->validationAttribute('roles'),

                Select::make('sede_id')
                    ->label('Sede')
                    ->options(function () {
                        $empresa = Filament::getTenant();

                        if (! $empresa) {
                            return [];
                        }

                        return Auth::user()->sedesAccesibles($empresa)->pluck('nombre', 'id');
                    })
                    ->visible(fn (Get $get) => self::algunRolRequiereSede($get('roles') ?? []))
                    ->required(fn (Get $get) => self::algunRolRequiereSede($get('roles') ?? []))
                    ->native(false),
            ]);
    }

    private static function algunRolRequiereSede(array $rolesSeleccionados): bool
    {
        return collect($rolesSeleccionados)
            ->contains(fn (string $valor) => Rol::tryFrom($valor)?->requiereSede() ?? true);
    }

    /**
     * Valida en el propio campo "roles" (no con ->unique(), que solo sirve
     * para una columna): administración central no se puede combinar con
     * otros roles en el mismo acceso (no tiene sede, los demás sí), y
     * ninguno de los roles marcados puede ya existir para este mismo
     * usuario+sede. Al editar, excluye el propio grupo que se está
     * reemplazando (ver el `->using()` de EditAction) — de lo contrario,
     * los roles que la persona ya tenía se marcarían como "duplicados".
     */
    private static function reglaRoles(Get $get, ?Acceso $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
            $roles = is_array($value) ? $value : [];

            if (count($roles) > 1 && in_array(Rol::AdministracionCentral->value, $roles, true)) {
                $fail('El rol de administración central no se puede combinar con otros roles en el mismo acceso.');

                return;
            }

            $userId = $get('user_id');
            $sedeId = $get('sede_id');
            $empresaId = Filament::getTenant()?->id;

            if (! $userId || ! $empresaId) {
                return;
            }

            $query = Acceso::query()
                ->where('user_id', $userId)
                ->where('empresa_id', $empresaId)
                ->where('sede_id', $sedeId);

            if ($record) {
                $query->where(fn ($q) => $q->where('user_id', '!=', $record->user_id)->orWhere('sede_id', '!=', $record->sede_id));
            }

            $yaExisten = $query->pluck('rol')->map(fn (Rol $rol) => $rol->value)->intersect($roles);

            if ($yaExisten->isNotEmpty()) {
                $etiquetas = $yaExisten->map(fn (string $valor) => Rol::from($valor)->label())->implode(', ');
                $fail("Este usuario ya tiene el rol \"{$etiquetas}\" en esta sede.");
            }
        };
    }
}
