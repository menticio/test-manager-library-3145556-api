<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Page;
use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\Errors\DomainError;
use App\Domain\Member;
use App\Domain\ValueObjects\DocumentNumber;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PgMemberRepository implements MemberRepository
{
    public function find(string $id): ?Member
    {
        if (! PgCategoryRepository::isUuid($id)) {
            return null;
        }
        $row = DB::table('member')->where('id', $id)->first();

        return $row === null ? null : self::toDomain($row);
    }

    public function documentExists(string $documentNumber): bool
    {
        return DB::table('member')->where('document_number', $documentNumber)->exists();
    }

    public function add(Member $member): void
    {
        Pg::unique(
            fn () => DB::table('member')->insert([
                'id' => $member->id,
                'full_name' => $member->fullName,
                'document_number' => $member->documentNumber->value,
            ]),
            new DomainError('DOCUMENT_TAKEN', "Ya existe un socio con el documento {$member->documentNumber->value}.",
                [['field' => 'document_number', 'issue' => 'repetido']]),
        );
    }

    public function search(?string $text, int $page, int $size): Page
    {
        $query = DB::table('member');
        if ($text !== null) {
            $like = Pg::likeContains($text);
            $query->where(fn (Builder $q) => $q->where('full_name', 'ilike', $like)->orWhere('document_number', 'ilike', $like));
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('full_name')->orderBy('id')->offset(($page - 1) * $size)->limit($size)->get();

        return new Page($rows->map(fn ($row) => self::toDomain($row))->all(), $page, $size, $total);
    }

    private static function toDomain(object $row): Member
    {
        return new Member($row->id, $row->full_name, new DocumentNumber($row->document_number));
    }
}
