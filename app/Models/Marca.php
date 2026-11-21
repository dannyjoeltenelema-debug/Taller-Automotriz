<?php

namespace App\Models;

use App\Models\Concerns\InvalidaCatalogos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marca extends Model
{
    use InvalidaCatalogos;

    protected $fillable = ['nombre'];

    public function modelos(): HasMany
    {
        return $this->hasMany(Modelo::class);
    }
}
