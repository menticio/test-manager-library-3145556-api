<?php

namespace App\Application\Ports\Outbound;

use RuntimeException;

/** Raised by LoanRepository when a book changed since it was read (ADR-002: optimistic concurrency). */
final class ConcurrencyConflict extends RuntimeException {}
