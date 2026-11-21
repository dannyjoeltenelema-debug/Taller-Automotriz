<?php

namespace App\Models;

use App\Models\Concerns\InvalidaCatalogos;
use Illuminate\Database\Eloquent\Model;

class ChecklistItem extends Model
{
    use InvalidaCatalogos;

    protected $fillable = ['nombre', 'activo', 'orden'];

    protected $casts = ['activo' => 'boolean'];
}
