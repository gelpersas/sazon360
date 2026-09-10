<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['empresa_id', 'categoria_id', 'nombre', 'descripcion', 'precio', 'estado', 'codigo_impuesto_dian', 'tasa_iva', 'imagen_path'])]
class Producto extends Model
{
    use HasFactory;

    // El POS táctil (catálogo retail) consume `imagen_url` directo del JSON
    // de la API — ver Pos\ReferenciaController::catalogo(), que no
    // transforma manualmente los productos.
    protected $appends = ['imagen_url'];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'tasa_iva' => 'decimal:2',
        ];
    }

    /**
     * asset() a propósito, no `Storage::disk('public')->url()`: ese último
     * antepone `APP_URL` tal cual (config/filesystems.php), que solo es
     * correcto si el request real llega exactamente por ese host — se
     * comprobó en local que rompía la imagen al navegar por
     * 127.0.0.1:8000 en vez del host de `APP_URL`. asset() en cambio arma
     * la URL a partir del host real del request entrante (sin
     * `URL::forceRootUrl()` en este proyecto), así que resuelve bien sin
     * importar el entorno (dev, staging, producción).
     */
    protected function imagenUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->imagen_path ? asset('storage/'.$this->imagen_path) : null,
        );
    }

    /**
     * Sin esto configurado, FactusProveedor no puede facturar el producto
     * ante la DIAN — ver docs/DECISIONES.md DEC-019.
     */
    public function tieneDatosFiscalesCompletos(): bool
    {
        return $this->codigo_impuesto_dian !== null && $this->tasa_iva !== null;
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function recetaItems(): HasMany
    {
        return $this->hasMany(RecetaItem::class);
    }
}
