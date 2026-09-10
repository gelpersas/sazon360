<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['empresa_id', 'sede_id', 'nombre', 'orden', 'estado', 'impresora_ip', 'impresora_puerto'])]
class AreaPreparacion extends Model
{
    use HasFactory;

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function comandas(): HasMany
    {
        return $this->hasMany(Comanda::class);
    }

    /**
     * Sin impresora configurada, esta área sigue funcionando solo con el
     * KDS (ver docs/DECISIONES.md) — imprimir es estrictamente opcional.
     */
    public function tieneImpresora(): bool
    {
        return filled($this->impresora_ip) && filled($this->impresora_puerto);
    }
}
