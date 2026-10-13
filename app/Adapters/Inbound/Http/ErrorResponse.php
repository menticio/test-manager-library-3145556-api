<?php

namespace App\Adapters\Inbound\Http;

use App\Domain\Errors\DomainError;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The single error body of api-contract.md §1: { code, message, details }. Only this adapter maps codes to HTTP statuses
 * (architecture.md §5). Never traces, SQL, server paths or passwords (RNF-4).
 */
final class ErrorResponse
{
    private const STATUS = [
        'INVALID_CREDENTIALS' => 401,
        'FORBIDDEN_ROLE' => 403,
        'NOT_FOUND' => 404,
        'USERNAME_TAKEN' => 409,
        'ISBN_TAKEN' => 409,
        'DOCUMENT_TAKEN' => 409,
        'BOOK_HAS_OPEN_LOANS' => 409,
        'BOOK_NOT_AVAILABLE' => 409,
        'MEMBER_HAS_OVERDUE' => 409,
        'ITEM_ALREADY_RETURNED' => 409,
        'MEMBER_LIMIT_EXCEEDED' => 422,
        'DUPLICATE_BOOK_IN_LOAN' => 422,
        'VALIDATION_ERROR' => 422,
        'INVALID_DATE_RANGE' => 422,
    ];

    public static function from(Throwable $e): JsonResponse
    {
        if ($e instanceof DomainError) {
            return self::make(self::STATUS[$e->errorCode] ?? 500, $e->errorCode, $e->getMessage(), $e->details);
        }
        if ($e instanceof HttpExceptionInterface && in_array($e->getStatusCode(), [404, 405], true)) {
            return self::make(404, 'NOT_FOUND', 'El recurso no existe.');
        }

        return self::make(500, 'INTERNAL_ERROR', 'Ocurrió un error interno. Intente de nuevo.');
    }

    /** @param list<array{field: string, issue: string}> $details */
    private static function make(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return new JsonResponse(['code' => $code, 'message' => $message, 'details' => $details], $status, [], JSON_UNESCAPED_UNICODE);
    }
}
