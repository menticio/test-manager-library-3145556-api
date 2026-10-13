<?php

namespace App\Application\Services;

use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Errors\DomainError;
use App\Domain\Loan;

/** CA-08.1: a loan with its lines and its computed status. */
final readonly class GetLoanService
{
    public function __construct(
        private LoanRepository $loans,
        private Clock $clock,
    ) {}

    /** @return array{loan: Loan, status: string} */
    public function get(string $id): array
    {
        $loan = $this->loans->find($id) ?? throw new DomainError('NOT_FOUND', 'El préstamo no existe.');

        return ['loan' => $loan, 'status' => $loan->status($this->clock->now())];
    }
}
