<?php

namespace App\Models;

use App\Enums\Rol;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

#[Fillable(['user_id', 'empresa_id', 'sede_id', 'rol'])]
class Acceso extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'rol' => Rol::class,
        ];
    }

    /**
     * Defensa adicional a AccesoPolicy/el formulario (ver .claude/rules/
     * seguridad.md, "no confiar únicamente en el frontend"): solo
     * administración central puede otorgar el rol de administración
     * central — mismo criterio que MovimientoCaja::booted(), que tampoco
     * confía solo en la Policy genérica de Filament.
     */
    protected static function booted(): void
    {
        static::saving(function (Acceso $acceso): void {
            if ($acceso->rol !== Rol::AdministracionCentral) {
                return;
            }

            // El primer acceso de una empresa recién creada siempre puede
            // ser administración central — es el autoregistro de quien la
            // funda (ver RegisterEmpresa::handleRegistration()), no alguien
            // ya con acceso otorgándose privilegios de más.
            $yaTieneAlgunAcceso = static::query()
                ->where('empresa_id', $acceso->empresa_id)
                ->when($acceso->exists, fn ($query) => $query->whereKeyNot($acceso->getKey()))
                ->exists();

            if (! $yaTieneAlgunAcceso) {
                return;
            }

            $empresa = $acceso->empresa ?? Empresa::find($acceso->empresa_id);

            if (Auth::check() && $empresa && ! Auth::user()->esAdminCentralDe($empresa)) {
                throw new AuthorizationException('Solo administración central puede asignar el rol de administración central.');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
