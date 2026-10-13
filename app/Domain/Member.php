<?php

namespace App\Domain;

use App\Domain\Errors\DomainError;
use App\Domain\ValueObjects\DocumentNumber;

/** A person who borrows books. Not a system user (spec.md §1). Members are never deleted (CA-05.3). */
final readonly class Member
{
    public string $fullName;

    public function __construct(
        public string $id,
        string $fullName,
        public DocumentNumber $documentNumber,
    ) {
        $fullName = trim($fullName);
        if ($fullName === '' || mb_strlen($fullName) > 200) {
            throw DomainError::validation('full_name', 'obligatorio, máximo 200 caracteres',
                'El nombre es obligatorio y admite máximo 200 caracteres.');
        }
        $this->fullName = $fullName;
    }
}
