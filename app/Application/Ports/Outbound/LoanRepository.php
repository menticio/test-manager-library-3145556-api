<?php

namespace App\Application\Ports\Outbound;

/** Port: loans. */
interface LoanRepository
{
    /** CA-03.5: lines of this book not yet returned. */
    public function countUnreturnedForBook(string $bookId): int;
}
