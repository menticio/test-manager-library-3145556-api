<?php

namespace App\Domain\Errors;

use DomainException;

/**
 * Business error raised by the domain or the application.
 * $errorCode is the `code` of api-contract.md §2; only the HTTP adapter maps it to a status (architecture.md §5).
 * Messages are in Spanish because they reach the user (Constitution, Article XI).
 */
final class DomainError extends DomainException
{
    /** @param list<array{field: string, issue: string}> $details */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function validation(string $field, string $issue, string $message): self
    {
        return new self('VALIDATION_ERROR', $message, [['field' => $field, 'issue' => $issue]]);
    }

    public static function invalidDateRange(): self
    {
        return new self('INVALID_DATE_RANGE', 'La fecha final no puede ser anterior a la fecha inicial.',
            [['field' => 'to', 'issue' => 'anterior a from']]);
    }
}
