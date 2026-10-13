<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\Errors\DomainError;
use App\Domain\Member;

/** GET /members/{id}: detail with active_loans, the count of books not yet returned (api-contract.md §3.5). */
final readonly class GetMemberService
{
    public function __construct(
        private MemberRepository $members,
        private LoanRepository $loans,
    ) {}

    /** @return array{member: Member, active_loans: int} */
    public function get(string $id): array
    {
        $member = $this->members->find($id) ?? throw new DomainError('NOT_FOUND', 'El socio no existe.');

        return ['member' => $member, 'active_loans' => count($this->loans->unreturnedItemsOfMember($id))];
    }
}
