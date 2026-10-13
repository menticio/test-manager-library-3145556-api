<?php

// T-16 evidence: the contract test walks every route of api-contract.md §3 and every code of §2
// against the API (Laravel kernel + real PostgreSQL, each test rolled back). RNF-4: one error body for every error.

use App\Adapters\Outbound\Persistence\PgUserRepository;
use App\Adapters\Outbound\Security\Argon2idPasswordHasher;
use App\Application\Ports\Outbound\Clock;
use App\Domain\Id;
use App\Domain\User;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FixedClock;

const NOVELA_ID = '11111111-1111-4111-8111-111111111111';
const PNG_BYTES = "\x89PNG\r\n\x1A\n0000";

function assertError(TestResponse $response, int $status, string $code): void
{
    $response->assertStatus($status)->assertJsonStructure(['code', 'message', 'details']);
    expect($response->json('code'))->toBe($code)
        ->and($response->json('message'))->toBeString()->not->toBeEmpty()
        ->and($response->getContent())->not->toContain('password')->not->toContain('SQLSTATE')->not->toContain('vendor/');
}

function bearer(string $token): array
{
    return ['Authorization' => "Bearer {$token}"];
}

beforeEach(function () {
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-14T14:15:00Z'));
    app()->instance(Clock::class, $this->clock);

    $hasher = new Argon2idPasswordHasher;
    $users = new PgUserRepository;
    $users->add(User::provisionAdmin(Id::new(), 'root', $hasher->hash('admin-pass')));
    $users->add(User::registerLibrarian(Id::new(), 'lib', $hasher->hash('lib-pass'), 'librarian'));

    $this->admin = bearer($this->postJson('/api/v1/auth/login', ['username' => 'root', 'password' => 'admin-pass'])->json('access_token'));
    $this->librarian = bearer($this->postJson('/api/v1/auth/login', ['username' => 'lib', 'password' => 'lib-pass'])->json('access_token'));

    $this->newBook = fn (string $isbn, int $copies = 2, string $title = 'Cien años de soledad') => $this->postJson('/api/v1/books',
        ['title' => $title, 'author' => 'García Márquez', 'isbn' => $isbn, 'category_id' => NOVELA_ID, 'total_copies' => $copies], $this->admin)->json('id');
    $this->newMember = fn (string $doc) => $this->postJson('/api/v1/members', ['full_name' => "Socio {$doc}", 'document_number' => $doc], $this->librarian)->json('id');
});

it('serves health without a token', function () {
    $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok']);
});

it('logs in with a Bearer token for 8 hours and rejects bad credentials with one message (HU-01)', function () {
    $this->postJson('/api/v1/auth/login', ['username' => 'Lib ', 'password' => 'lib-pass'])
        ->assertOk()->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'role'])
        ->assertJson(['token_type' => 'Bearer', 'expires_in' => 28800, 'role' => 'librarian']);

    $wrongUser = $this->postJson('/api/v1/auth/login', ['username' => 'nobody', 'password' => 'lib-pass']);
    $wrongPass = $this->postJson('/api/v1/auth/login', ['username' => 'lib', 'password' => 'bad']);
    assertError($wrongUser, 401, 'INVALID_CREDENTIALS');
    assertError($wrongPass, 401, 'INVALID_CREDENTIALS');
    expect($wrongUser->json('message'))->toBe($wrongPass->json('message'));
});

it('requires a token everywhere else (401) and the right role (403) (CA-01.3)', function () {
    assertError($this->getJson('/api/v1/books'), 401, 'INVALID_CREDENTIALS');
    assertError($this->getJson('/api/v1/books', bearer('forged.token.value')), 401, 'INVALID_CREDENTIALS');
    assertError($this->postJson('/api/v1/users', ['username' => 'x', 'password' => 'y', 'role' => 'librarian'], $this->librarian), 403, 'FORBIDDEN_ROLE');
    assertError($this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-31', $this->librarian), 403, 'FORBIDDEN_ROLE');

    $this->clock->advance('+8 hours +1 second');
    assertError($this->getJson('/api/v1/categories', $this->librarian), 401, 'INVALID_CREDENTIALS');
});

it('creates librarians without exposing the password (HU-02)', function () {
    $this->postJson('/api/v1/users', ['username' => 'Ana ', 'password' => 'secret-1', 'role' => 'librarian'], $this->admin)
        ->assertCreated()->assertJson(['username' => 'ana', 'role' => 'librarian'])->assertJsonStructure(['id', 'username', 'role'])
        ->assertJsonMissingPath('password')->assertJsonMissingPath('password_hash');

    assertError($this->postJson('/api/v1/users', ['username' => 'ANA', 'password' => 'secret-2', 'role' => 'librarian'], $this->admin), 409, 'USERNAME_TAKEN');
    assertError($this->postJson('/api/v1/users', ['username' => 'eve', 'password' => 'secret-3', 'role' => 'admin'], $this->admin), 422, 'VALIDATION_ERROR');
});

it('lists the five categories ordered by name', function () {
    expect(array_column($this->getJson('/api/v1/categories', $this->librarian)->assertOk()->json('items'), 'name'))
        ->toBe(['Ciencia', 'Historia', 'Infantil', 'Novela', 'Tecnología']);
});

it('manages books with the contract shape (HU-03, HU-04)', function () {
    $created = $this->postJson('/api/v1/books', ['title' => 'Cien años de soledad', 'author' => 'García Márquez',
        'isbn' => '9780307474728', 'category_id' => NOVELA_ID, 'total_copies' => 4], $this->admin);
    $created->assertCreated()->assertExactJson([
        'id' => $created->json('id'), 'title' => 'Cien años de soledad', 'author' => 'García Márquez', 'isbn' => '9780307474728',
        'category' => ['id' => NOVELA_ID, 'name' => 'Novela'], 'total_copies' => 4, 'available_copies' => 4, 'cover_key' => null,
    ]);
    $id = $created->json('id');

    assertError($this->postJson('/api/v1/books', ['title' => 'Otro', 'author' => 'X', 'isbn' => '9780307474728', 'category_id' => NOVELA_ID, 'total_copies' => 1], $this->admin), 409, 'ISBN_TAKEN');
    assertError($this->postJson('/api/v1/books', ['title' => 'Sin ISBN'], $this->admin), 422, 'VALIDATION_ERROR');
    assertError($this->postJson('/api/v1/books', ['title' => 'T', 'author' => 'A', 'isbn' => '9780000000009', 'category_id' => NOVELA_ID, 'total_copies' => 1], $this->librarian), 403, 'FORBIDDEN_ROLE');

    $this->getJson('/api/v1/books?q=cien&page=1&size=10', $this->librarian)->assertOk()
        ->assertJson(['page' => 1, 'size' => 10, 'total' => 1])->assertJsonPath('items.0.id', $id);
    assertError($this->getJson('/api/v1/books?size=51', $this->librarian), 422, 'VALIDATION_ERROR');
    $this->getJson("/api/v1/books/{$id}", $this->librarian)->assertOk()->assertJsonPath('isbn', '9780307474728');

    $this->putJson("/api/v1/books/{$id}", ['title' => 'Cien años', 'author' => 'GGM', 'category_id' => NOVELA_ID, 'total_copies' => 6], $this->admin)
        ->assertOk()->assertJson(['title' => 'Cien años', 'total_copies' => 6, 'available_copies' => 6]);

    $this->deleteJson("/api/v1/books/{$id}", [], $this->admin)->assertNoContent();
    assertError($this->getJson("/api/v1/books/{$id}", $this->librarian), 404, 'NOT_FOUND');
});

it('uploads, serves and removes a cover (CA-03.4)', function () {
    $id = ($this->newBook)('9780307474728');
    $boundary = 'XBOUNDARY';
    $body = "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"c.png\"\r\nContent-Type: image/png\r\n\r\n".PNG_BYTES."\r\n--{$boundary}--\r\n";
    $headers = fn (array $auth) => ['CONTENT_TYPE' => "multipart/form-data; boundary={$boundary}", 'HTTP_AUTHORIZATION' => $auth['Authorization']];

    $this->call('PUT', "/api/v1/books/{$id}/cover", [], [], [], $headers($this->admin), $body)->assertOk()->assertJsonPath('id', $id);
    $cover = $this->get("/api/v1/books/{$id}/cover", $this->librarian)->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($cover->getContent())->toBe(PNG_BYTES);

    $notImage = "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"c.png\"\r\n\r\nGIF89a\r\n--{$boundary}--\r\n";
    assertError($this->call('PUT', "/api/v1/books/{$id}/cover", [], [], [], $headers($this->admin), $notImage), 422, 'VALIDATION_ERROR');

    $this->deleteJson("/api/v1/books/{$id}/cover", [], $this->admin)->assertNoContent();
    $this->getJson("/api/v1/books/{$id}", $this->librarian)->assertJsonPath('cover_key', null);
    assertError($this->getJson("/api/v1/books/{$id}/cover", $this->librarian), 404, 'NOT_FOUND');
});

it('manages members (HU-05)', function () {
    $created = $this->postJson('/api/v1/members', ['full_name' => 'Laura Gómez', 'document_number' => '1020304050'], $this->librarian)
        ->assertCreated()->assertJson(['full_name' => 'Laura Gómez', 'document_number' => '1020304050']);
    assertError($this->postJson('/api/v1/members', ['full_name' => 'Otra', 'document_number' => '1020304050'], $this->admin), 409, 'DOCUMENT_TAKEN');

    $this->getJson('/api/v1/members?q=laura', $this->librarian)->assertOk()->assertJson(['total' => 1]);
    $this->getJson('/api/v1/members/'.$created->json('id'), $this->librarian)->assertOk()->assertJson(['active_loans' => 0]);
    assertError($this->getJson('/api/v1/members/00000000-0000-4000-8000-000000000000', $this->librarian), 404, 'NOT_FOUND');
});

it('registers, shows, lists and returns loans with every business error (HU-06, HU-07, HU-08)', function () {
    $member = ($this->newMember)('1');
    $book = ($this->newBook)('9780000000001', 1, 'Último ejemplar');

    $loan = $this->postJson('/api/v1/loans', ['member_id' => $member, 'items' => [['book_id' => $book]]], $this->librarian);
    $loan->assertCreated()->assertJson(['member_id' => $member, 'loaned_at' => '2026-10-14T14:15:00Z', 'status' => 'open'])
        ->assertJsonPath('items.0.due_at', '2026-10-28T14:15:00Z')
        ->assertJsonPath('items.0.book_title', 'Último ejemplar')
        ->assertJsonPath('items.0.category_name', 'Novela')
        ->assertJsonPath('items.0.returned_at', null);
    $loanId = $loan->json('id');
    $itemId = $loan->json('items.0.id');

    assertError($this->postJson('/api/v1/loans', ['member_id' => ($this->newMember)('2'), 'items' => [['book_id' => $book]]], $this->librarian), 409, 'BOOK_NOT_AVAILABLE');
    $other = ($this->newBook)('9780000000002');
    assertError($this->postJson('/api/v1/loans', ['member_id' => $member, 'items' => [['book_id' => $other], ['book_id' => $other]]], $this->librarian), 422, 'DUPLICATE_BOOK_IN_LOAN');
    assertError($this->postJson('/api/v1/loans', ['member_id' => $member, 'items' => [
        ['book_id' => $other], ['book_id' => ($this->newBook)('9780000000003')], ['book_id' => ($this->newBook)('9780000000004')]]], $this->librarian), 422, 'MEMBER_LIMIT_EXCEEDED');
    assertError($this->postJson('/api/v1/loans', ['member_id' => $member, 'items' => []], $this->librarian), 422, 'VALIDATION_ERROR');
    assertError($this->deleteJson("/api/v1/books/{$book}", [], $this->admin), 409, 'BOOK_HAS_OPEN_LOANS');

    $this->getJson("/api/v1/loans/{$loanId}", $this->librarian)->assertOk()->assertJson(['id' => $loanId, 'status' => 'open']);
    $this->getJson("/api/v1/loans?member_id={$member}&status=open", $this->librarian)->assertOk()->assertJson(['total' => 1]);

    $this->clock->advance('+15 days');
    $this->getJson('/api/v1/loans/overdue', $this->librarian)->assertOk()
        ->assertJsonPath('items.0.loan_id', $loanId)->assertJsonPath('items.0.member_name', 'Socio 1')->assertJsonPath('items.0.days_late', 1);
    assertError($this->postJson('/api/v1/loans', ['member_id' => $member, 'items' => [['book_id' => $other]]], $this->librarian), 409, 'MEMBER_HAS_OVERDUE');

    $this->postJson("/api/v1/loans/{$loanId}/items/{$itemId}/return", [], $this->librarian)->assertOk()
        ->assertJson(['id' => $itemId, 'returned_at' => '2026-10-29T14:15:00Z']);
    assertError($this->postJson("/api/v1/loans/{$loanId}/items/{$itemId}/return", [], $this->librarian), 409, 'ITEM_ALREADY_RETURNED');
    $this->getJson("/api/v1/books/{$book}", $this->librarian)->assertJson(['available_copies' => 1]);
    $this->getJson("/api/v1/loans/{$loanId}", $this->librarian)->assertJson(['status' => 'closed']);
});

it('builds the most-borrowed report for admins only, without members (HU-10)', function () {
    $book = ($this->newBook)('9780000000001');
    $this->postJson('/api/v1/loans', ['member_id' => ($this->newMember)('1'), 'items' => [['book_id' => $book]]], $this->librarian)->assertCreated();

    $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-31', $this->admin)->assertOk()
        ->assertExactJson(['from' => '2026-10-01', 'to' => '2026-10-31', 'rows' => [
            ['book_id' => $book, 'book_title' => 'Cien años de soledad', 'category_name' => 'Novela', 'times_borrowed' => 1],
        ]]);
    assertError($this->getJson('/api/v1/reports/most-borrowed?from=2026-10-31&to=2026-10-01', $this->admin), 422, 'INVALID_DATE_RANGE');
});

it('answers unknown routes and malformed ids with the same error body (RNF-4)', function () {
    assertError($this->getJson('/api/v1/nope', $this->admin), 404, 'NOT_FOUND');
    assertError($this->getJson('/api/v1/books/not-a-uuid', $this->admin), 404, 'NOT_FOUND');
    assertError($this->getJson('/api/v1/loans/not-a-uuid', $this->admin), 404, 'NOT_FOUND');
});
