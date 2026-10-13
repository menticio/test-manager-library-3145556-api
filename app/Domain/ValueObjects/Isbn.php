<?php

namespace App\Domain\ValueObjects;

use App\Domain\Errors\DomainError;

/** ISBN: exactly 13 digits (CA-03.2, data-model.md §3 "solo dígitos"). No checksum rule: the spec does not ask for one. */
final readonly class Isbn
{
    public string $value;

    public function __construct(string $value)
    {
        if (preg_match('/^\d{13}$/', $value) !== 1) {
            throw DomainError::validation('isbn', 'debe tener 13 dígitos', 'El ISBN debe tener exactamente 13 dígitos.');
        }
        $this->value = $value;
    }
}
