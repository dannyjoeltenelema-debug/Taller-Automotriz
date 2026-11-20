<?php

namespace App\Rules;

use App\Support\Placa;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PlacaEcuador implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Placa::pareceValida($value)) {
            $fail('La placa no tiene un formato válido (ej. PBA-4521).');
        }
    }
}
