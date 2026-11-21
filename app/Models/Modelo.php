<?php

namespace App\Models;

use App\Models\Concerns\InvalidaCatalogos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modelo extends Model
{
    use InvalidaCatalogos;

    protected $fillable = ['marca_id', 'nombre'];

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    /** Busca o crea marca + modelo por nombre (alta rápida). */
    public static function resolver(string $marca, string $modelo): self
    {
        $m = Marca::firstOrCreate(['nombre' => mb_convert_case(trim($marca), MB_CASE_TITLE)]);

        return $m->modelos()->firstOrCreate(['nombre' => trim($modelo)]);
    }
}
