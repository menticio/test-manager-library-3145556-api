<?php

namespace App\Domain\ValueObjects;

use App\Domain\Errors\DomainError;

/** A count that must be at least 1 (CA-03.3: total de ejemplares >= 1). */
final readonly class Quantity
{
    public int $value;

    public function __construct(int $value, string $field = 'total_copies')
    {
        if ($value < 1) {
            throw DomainError::validation($field, 'debe ser mayor o igual a 1', 'La cantidad debe ser mayor o igual a 1.');
        }
        $this->value = $value;
    }
}
