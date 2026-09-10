<?php

namespace App\Models;

use App\Enums\Rol;
use App\Enums\TemaPreferencia;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'tema'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'tema' => TemaPreferencia::class,
        ];
    }

    /**
     * Guardado del lado del servidor de la preferencia de tema — Filament y
     * el POS táctil comparten este mismo método (ver rutas `PATCH /tema` y
     * `PATCH /api/pos/me/tema`) para que la preferencia siga al usuario
     * entre ambas superficies y entre dispositivos, no solo en el
     * localStorage del navegador actual.
     */
    public function actualizarTema(TemaPreferencia $tema): void
    {
        $this->update(['tema' => $tema]);
    }

    public function accesos(): HasMany
    {
        return $this->hasMany(Acceso::class);
    }

    /**
     * Empresas donde el usuario tiene rol de administración (central o de
     * sede) — las únicas relevantes para el panel de Filament.
     */
    public function empresas(): Collection
    {
        $empresaIds = $this->accesos()
            ->whereIn('rol', [Rol::AdministracionCentral, Rol::AdministracionSede])
            ->pluck('empresa_id');

        return Empresa::query()->whereIn('id', $empresaIds)->get();
    }

    public function tieneAccesoAEmpresa(Empresa $empresa): bool
    {
        return $this->accesos()->where('empresa_id', $empresa->id)->exists();
    }

    public function esAdminCentralDe(Empresa $empresa): bool
    {
        return $this->accesos()
            ->where('empresa_id', $empresa->id)
            ->where('rol', Rol::AdministracionCentral)
            ->exists();
    }

    public function rolEnSede(Sede $sede): ?Rol
    {
        // Admin central siempre gana: si además tuviera un acceso específico
        // en esta sede (ej. caja), no debe perder privilegios por eso.
        if ($this->esAdminCentralDe($sede->empresa)) {
            return Rol::AdministracionCentral;
        }

        return $this->accesos()->where('sede_id', $sede->id)->first()?->rol;
    }

    /**
     * Todos los roles del usuario en una sede — un usuario puede tener más
     * de un `Acceso` ahí si el administrador se lo asigna (ej. Caja +
     * Mesero a la vez, para alguien que cubre ambas funciones). A
     * diferencia de `rolEnSede()` (pensado para mostrar "el" rol de
     * alguien, ej. en el sidebar del POS), esto es lo que deben consultar
     * los permisos del POS táctil — de lo contrario, un segundo `Acceso`
     * quedaría ignorado en silencio (`rolEnSede()` solo devuelve el
     * primero que encuentra).
     */
    public function rolesEnSede(Sede $sede): Collection
    {
        if ($this->esAdminCentralDe($sede->empresa)) {
            return collect([Rol::AdministracionCentral]);
        }

        return $this->accesos()->where('sede_id', $sede->id)->pluck('rol');
    }

    /**
     * Los 3 dominios del POS táctil (ver App\Enums\Rol::accedeA*() y
     * docs/DECISIONES.md DEC-043) evaluados sobre TODOS los roles del
     * usuario en la sede, no solo uno — así un usuario con varios roles
     * obtiene la suma de lo que cada uno permite, en vez de que el
     * segundo rol quede ignorado.
     */
    public function accedeAMostradorEnSede(Sede $sede): bool
    {
        return $this->rolesEnSede($sede)->contains(fn (Rol $rol) => $rol->accedeAMostrador());
    }

    public function accedeACocinaEnSede(Sede $sede): bool
    {
        return $this->rolesEnSede($sede)->contains(fn (Rol $rol) => $rol->accedeACocina());
    }

    public function accedeACajaEnSede(Sede $sede): bool
    {
        return $this->rolesEnSede($sede)->contains(fn (Rol $rol) => $rol->accedeACaja());
    }

    /**
     * Sedes visibles para el usuario dentro de una empresa: todas si es
     * administración central, o solo aquellas con acceso explícito.
     */
    public function sedesAccesibles(Empresa $empresa): Collection
    {
        if ($this->esAdminCentralDe($empresa)) {
            return $empresa->sedes;
        }

        $sedeIds = $this->accesos()
            ->where('empresa_id', $empresa->id)
            ->whereNotNull('sede_id')
            ->pluck('sede_id');

        return Sede::query()->whereIn('id', $sedeIds)->get();
    }

    /**
     * Sedes donde el usuario puede operar el POS táctil — cualquier rol
     * (caja, mesero, área de preparación, administración), a diferencia de
     * `sedesAccesibles()` que exige una `Empresa` y solo cuenta para
     * Filament. Se usa para poblar el selector de sede al iniciar sesión en
     * el POS (ver docs/DECISIONES.md DEC-011).
     */
    public function sedesOperativas(): Collection
    {
        $sedeIds = $this->accesos()->whereNotNull('sede_id')->pluck('sede_id');
        $sedesDirectas = Sede::query()->whereIn('id', $sedeIds)->get();

        $empresaIds = $this->accesos()->where('rol', Rol::AdministracionCentral)->pluck('empresa_id');
        $sedesPorEmpresa = Sede::query()->whereIn('empresa_id', $empresaIds)->get();

        return $sedesDirectas->merge($sedesPorEmpresa)->unique('id')->values();
    }

    /**
     * Filament es solo para administración (central o de sede); caja, mesero
     * y área de preparación operan el POS táctil, no este panel. Un usuario
     * sin ningún acceso todavía (recién creado) también puede entrar — es el
     * único modo de llegar a "Registrar empresa" (/admin/new) y crear su
     * primera empresa; sin esto, ese flujo es inalcanzable.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->accesos()
            ->whereIn('rol', [Rol::AdministracionCentral, Rol::AdministracionSede])
            ->exists()
            || ! $this->accesos()->exists();
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->empresas();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Empresa && $this->empresas()->contains('id', $tenant->id);
    }
}
