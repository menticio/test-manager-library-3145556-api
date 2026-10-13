<?php

namespace App\Application\Ports\Outbound;

use App\Domain\LoanItem;

/** Port: loans. Loans and returns are never deleted (RN-5): there is no delete. */
interface LoanRepository
{
    /** CA-03.5: lines of this book not yet returned. */
    public function countUnreturnedForBook(string $bookId): int;

    /** Q7: lines of a member not yet returned (count and overdue check, CA-06.4, CA-06.5). @return list<LoanItem> */
    public function unreturnedItemsOfMember(string $memberId): array;
}
