<?php

// HU-05 evidence: members are created, listed and searched; the document is unique; members are never deleted.

use App\Application\Ports\Outbound\MemberRepository;
use App\Application\Services\CreateMemberService;
use App\Application\Services\GetMemberService;
use App\Application\Services\SearchMembersService;
use App\Domain\Errors\DomainError;
use App\Domain\Loan;
use App\Domain\Book;
use App\Domain\Id;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;
use Tests\Fakes\InMemoryLoanRepository;
use Tests\Fakes\InMemoryMemberRepository;

beforeEach(function () {
    $this->members = new InMemoryMemberRepository;
    $this->loans = new InMemoryLoanRepository;
    $this->create = new CreateMemberService($this->members);
});

it('creates a member with name and document (CA-05.1)', function () {
    $member = $this->create->create('Laura Gómez', '1020304050');
    expect($this->members->find($member->id)->fullName)->toBe('Laura Gómez');
});

it('rejects a repeated document with DOCUMENT_TAKEN (CA-05.1)', function () {
    $this->create->create('Laura Gómez', '1020304050');
    try {
        $this->create->create('Otra Persona', ' 1020304050 ');
        $this->fail('expected DOCUMENT_TAKEN');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('DOCUMENT_TAKEN');
    }
});

it('searches by name or document, paginated (CA-05.2)', function () {
    $this->create->create('Laura Gómez', '1020304050');
    $this->create->create('Pedro Ruiz', '7788990011');
    $search = new SearchMembersService($this->members);
    expect($search->search('laura', null, null)->total)->toBe(1)
        ->and($search->search('7788', null, null)->items[0]->fullName)->toBe('Pedro Ruiz')
        ->and($search->search(null, 1, 1)->total)->toBe(2);
});

it('shows active_loans as the count of books not yet returned (api-contract.md 3.5)', function () {
    $member = $this->create->create('Laura Gómez', '1020304050');
    $book = Book::create(Id::new(), 'A', 'X', new Isbn('9780307474728'), 'c', new Quantity(2));
    [$loan] = Loan::lend(Id::new(), $member->id, 'u', new DateTimeImmutable('2026-10-13T00:00:00Z'), [['book' => $book, 'category_name' => 'Novela']]);
    $this->loans->loans[$loan->id] = $loan;

    expect((new GetMemberService($this->members, $this->loans))->get($member->id)['active_loans'])->toBe(1);
});

it('offers no way to delete a member (CA-05.3)', function () {
    expect(get_class_methods(MemberRepository::class))->not->toContain('delete')->not->toContain('remove');
});
