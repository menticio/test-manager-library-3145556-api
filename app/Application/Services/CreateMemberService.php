<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\Errors\DomainError;
use App\Domain\Id;
use App\Domain\Member;
use App\Domain\ValueObjects\DocumentNumber;

/** CA-05.1: admin and librarian create members; the document is unique (409). */
final readonly class CreateMemberService
{
    public function __construct(private MemberRepository $members) {}

    public function create(string $fullName, string $documentNumber): Member
    {
        $member = new Member(Id::new(), $fullName, new DocumentNumber($documentNumber));
        if ($this->members->documentExists($member->documentNumber->value)) {
            throw new DomainError('DOCUMENT_TAKEN', "Ya existe un socio con el documento {$member->documentNumber->value}.",
                [['field' => 'document_number', 'issue' => 'repetido']]);
        }

        $this->members->add($member);

        return $member;
    }
}
