<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Page;
use App\Application\Ports\Outbound\ConcurrencyConflict;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\LoanItem;
use App\Domain\ValueObjects\DateRange;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Loans in library.loan and library.loan_item.
 * ADR-002: the version witness of a book is its available_copies as read; a book row is written only if it still has
 * that value. No version column is added (data-model.md §3 does not list one). ck_book_available_range is the last barrier.
 */
final class PgLoanRepository implements LoanRepository
{
    public function countUnreturnedForBook(string $bookId): int
    {
        return DB::table('loan_item')->where('book_id', $bookId)->whereNull('returned_at')->count();
    }

    public function unreturnedItemsOfMember(string $memberId): array
    {
        return DB::table('loan_item')
            ->join('loan', 'loan.id', '=', 'loan_item.loan_id')
            ->where('loan.member_id', $memberId)
            ->whereNull('loan_item.returned_at')
            ->get(['loan_item.*'])
            ->map(fn ($row) => self::item($row))->all();
    }

    public function addLoan(Loan $loan, array $books): void
    {
        DB::transaction(function () use ($loan, $books) {
            foreach ($books as $book) {
                // Withdrawn by one: it must still hold the value that was read (available + 1).
                self::writeCopies($book, $book->availableCopies + 1);
            }
            DB::table('loan')->insert([
                'id' => $loan->id,
                'member_id' => $loan->memberId,
                'loaned_by_user_id' => $loan->loanedByUserId,
                'loaned_at' => Pg::toTimestamp($loan->loanedAt),
            ]);
            DB::table('loan_item')->insert(array_map(fn (LoanItem $item) => [
                'id' => $item->id,
                'loan_id' => $loan->id,
                'book_id' => $item->bookId,
                'book_title' => $item->bookTitle,
                'isbn' => $item->isbn,
                'category_name' => $item->categoryName,
                'due_at' => Pg::toTimestamp($item->dueAt),
                'returned_at' => null,
            ], $loan->items));
        });
    }

    public function find(string $id): ?Loan
    {
        if (! PgCategoryRepository::isUuid($id)) {
            return null;
        }
        $row = DB::table('loan')->where('id', $id)->first();

        return $row === null ? null : self::loans([$row])[0];
    }

    public function saveReturn(Loan $loan, string $itemId, Book $book): void
    {
        $item = $loan->item($itemId);
        DB::transaction(function () use ($loan, $item, $book) {
            // CA-07.2: only while returned_at is still null, so a line never adds a copy twice.
            $updated = DB::table('loan_item')
                ->where('id', $item->id)->where('loan_id', $loan->id)->whereNull('returned_at')
                ->update(['returned_at' => Pg::toTimestamp($item->returnedAt)]);
            if ($updated !== 1) {
                throw new ConcurrencyConflict('Loan line already returned.');
            }
            // Restocked by one: it must still hold the value that was read (available - 1).
            self::writeCopies($book, $book->availableCopies - 1);
        });
    }

    public function search(?DateRange $range, ?string $status, ?string $memberId, DateTimeImmutable $now, int $page, int $size): Page
    {
        $query = DB::table('loan');
        if ($range !== null) {
            $query->where('loaned_at', '>=', Pg::toTimestamp($range->from))
                ->where('loaned_at', '<', Pg::toTimestamp($range->to->modify('+1 day')));
        }
        if ($memberId !== null) {
            PgCategoryRepository::isUuid($memberId) ? $query->where('member_id', $memberId) : $query->whereRaw('false');
        }
        $pending = fn (Builder $q) => $q->from('loan_item')->whereColumn('loan_item.loan_id', 'loan.id')->whereNull('returned_at');
        $late = fn (Builder $q) => $pending($q)->where('due_at', '<', Pg::toTimestamp($now));
        match ($status) {
            Loan::CLOSED => $query->whereNotExists($pending),
            Loan::OVERDUE => $query->whereExists($late),
            Loan::OPEN => $query->whereExists($pending)->whereNotExists($late),
            default => null,
        };

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('loaned_at')->orderBy('id')->offset(($page - 1) * $size)->limit($size)->get()->all();

        return new Page(self::loans($rows), $page, $size, $total);
    }

    /** Writes available_copies only if the row still holds $expected (optimistic concurrency, ADR-002). */
    private static function writeCopies(Book $book, int $expected): void
    {
        $updated = DB::table('book')
            ->where('id', $book->id)->whereNull('deleted_at')->where('available_copies', $expected)
            ->update(['available_copies' => $book->availableCopies]);
        if ($updated !== 1) {
            throw new ConcurrencyConflict("Book {$book->id} changed since it was read.");
        }
    }

    /** @param list<object> $rows @return list<Loan> */
    private static function loans(array $rows): array
    {
        $ids = array_map(fn ($row) => $row->id, $rows);
        $items = [];
        foreach (DB::table('loan_item')->whereIn('loan_id', $ids)->orderBy('due_at')->orderBy('id')->get() as $row) {
            $items[$row->loan_id][] = self::item($row);
        }

        return array_map(fn ($row) => Loan::reconstitute($row->id, $row->member_id, $row->loaned_by_user_id,
            Pg::fromTimestamp($row->loaned_at), $items[$row->id] ?? []), $rows);
    }

    private static function item(object $row): LoanItem
    {
        return new LoanItem($row->id, $row->book_id, $row->book_title, $row->isbn, $row->category_name,
            Pg::fromTimestamp($row->due_at), Pg::fromTimestamp($row->returned_at));
    }
}
