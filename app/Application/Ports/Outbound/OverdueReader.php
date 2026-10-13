<?php

namespace App\Application\Ports\Outbound;

use DateTimeImmutable;

/** Read port (Q8): lines not returned whose due_at is before now. Operational list: it does include the member name. */
interface OverdueReader
{
    /** @return list<array{loan_id: string, item_id: string, book_title: string, member_name: string, due_at: DateTimeImmutable}> */
    public function unreturnedDueBefore(DateTimeImmutable $now): array;
}
