<?php

namespace App\Domain\ValueObjects;

use App\Domain\Errors\DomainError;

/** Member document: required, trimmed, up to 30 characters (data-model.md §3). Uniqueness is checked by the engine (CA-05.1). */
final readonly class DocumentNumber
{
    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 30) {
            throw DomainError::validation('document_number', 'obligatorio, máximo 30 caracteres',
                'El documento es obligatorio y admite máximo 30 caracteres.');
        }
        $this->value = $value;
    }
}
