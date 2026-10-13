<?php

namespace App\Application\Ports\Outbound;

use App\Application\Page;
use App\Domain\Member;

/** Port: members. There is no delete: members are never removed (CA-05.3). */
interface MemberRepository
{
    public function find(string $id): ?Member;

    public function documentExists(string $documentNumber): bool;

    /** Throws DomainError DOCUMENT_TAKEN if the engine unique index rejects it. */
    public function add(Member $member): void;

    /** CA-05.2: by name or document, paginated. @return Page<Member> */
    public function search(?string $text, int $page, int $size): Page;
}
