<?php

namespace App\Adapters\Inbound\Http;

use App\Domain\Errors\DomainError;
use Illuminate\Http\Request;

/** Reads and checks request fields; any invalid field is 422 VALIDATION_ERROR with its field name (api-contract.md §2). */
final class Input
{
    public static function string(Request $request, string $field): string
    {
        $value = $request->json($field);
        if (! is_string($value) || trim($value) === '') {
            throw DomainError::validation($field, 'obligatorio', "El campo {$field} es obligatorio.");
        }

        return $value;
    }

    public static function int(Request $request, string $field): int
    {
        $value = $request->json($field);
        if (! is_int($value)) {
            throw DomainError::validation($field, 'debe ser un número entero', "El campo {$field} debe ser un número entero.");
        }

        return $value;
    }

    public static function optionalQuery(Request $request, string $field): ?string
    {
        $value = $request->query($field);
        if ($value !== null && ! is_string($value)) {
            throw DomainError::validation($field, 'inválido', "El parámetro {$field} no es válido.");
        }

        return $value === '' ? null : $value;
    }

    public static function optionalQueryInt(Request $request, string $field): ?int
    {
        $value = self::optionalQuery($request, $field);
        if ($value === null) {
            return null;
        }
        if (preg_match('/^\d{1,6}$/', $value) !== 1) {
            throw DomainError::validation($field, 'debe ser un número entero', "El parámetro {$field} debe ser un número entero.");
        }

        return (int) $value;
    }

    /** POST /loans items: list of { book_id }. @return list<string> */
    public static function bookIds(Request $request): array
    {
        $items = $request->json('items');
        if (! is_array($items) || ! array_is_list($items)) {
            throw DomainError::validation('items', 'obligatorio', 'El campo items es obligatorio.');
        }
        $ids = [];
        foreach ($items as $i => $item) {
            if (! is_array($item) || ! is_string($item['book_id'] ?? null) || $item['book_id'] === '') {
                throw DomainError::validation("items[{$i}].book_id", 'obligatorio', 'Cada línea necesita un book_id.');
            }
            $ids[] = $item['book_id'];
        }

        return $ids;
    }

    /** PUT /books/{id}/cover sends multipart/form-data with field "file". PHP does not parse multipart on PUT, so it is read here. */
    public static function uploadedFile(Request $request): string
    {
        if ($file = $request->file('file')) {
            return (string) file_get_contents($file->getRealPath());
        }
        if (preg_match('/boundary=(?:"([^"]+)"|([^;\s]+))/i', (string) $request->header('Content-Type'), $m) === 1) {
            $boundary = $m[1] !== '' ? $m[1] : $m[2];
            foreach (explode('--'.$boundary, $request->getContent()) as $part) {
                [$headers, $body] = array_pad(explode("\r\n\r\n", ltrim($part, "\r\n"), 2), 2, null);
                if ($body !== null && preg_match('/name="file"/i', $headers) === 1) {
                    return str_ends_with($body, "\r\n") ? substr($body, 0, -2) : $body;
                }
            }
        }

        throw DomainError::validation('file', 'obligatorio', 'Debe enviar la portada en el campo file.');
    }
}
