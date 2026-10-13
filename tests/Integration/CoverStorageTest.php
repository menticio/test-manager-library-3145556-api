<?php

// T-15 evidence: PNG of 2 MB ok; 3 MB -> 422; without cover -> cover_key null (disk adapter + PostgreSQL).

use App\Adapters\Outbound\Persistence\PgBookRepository;
use App\Adapters\Outbound\Storage\DiskCoverStorage;
use App\Application\Services\GetCoverService;
use App\Application\Services\RemoveCoverService;
use App\Application\Services\UploadCoverService;
use App\Domain\Book;
use App\Domain\Errors\DomainError;
use App\Domain\Id;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\Quantity;

const PNG_SIGNATURE = "\x89PNG\r\n\x1A\n";

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/covers-'.bin2hex(random_bytes(4));
    $this->storage = new DiskCoverStorage($this->dir);
    $this->books = new PgBookRepository;
    $this->book = Book::create(Id::new(), 'A', 'X', new Isbn('9780000000001'), '11111111-1111-4111-8111-111111111111', new Quantity(1));
    $this->books->add($this->book);
    $this->upload = new UploadCoverService($this->books, $this->storage);
});

afterEach(function () {
    array_map('unlink', glob($this->dir.'/*') ?: []);
    @rmdir($this->dir);
});

it('starts without cover: cover_key is null, never an empty string (CA-03.4)', function () {
    expect($this->books->findActive($this->book->id)->coverKey)->toBeNull();
});

it('stores a 2 MB PNG on disk and keeps only an opaque key in the database (D-08)', function () {
    $bytes = PNG_SIGNATURE.str_repeat("\0", 2 * 1024 * 1024 - 8);
    $key = $this->upload->upload($this->book->id, $bytes)->coverKey;

    expect($key)->toMatch('/^[0-9a-f]{32}\.png$/')
        ->and($this->books->findActive($this->book->id)->coverKey)->toBe($key)
        ->and($this->storage->get($key))->toBe($bytes)
        ->and((new GetCoverService($this->books, $this->storage))->get($this->book->id)['content_type'])->toBe('image/png');
});

it('rejects a 3 MB image with 422 VALIDATION_ERROR and stores nothing', function () {
    try {
        $this->upload->upload($this->book->id, PNG_SIGNATURE.str_repeat("\0", 3 * 1024 * 1024));
        $this->fail('expected VALIDATION_ERROR');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('VALIDATION_ERROR');
    }
    expect(glob($this->dir.'/*') ?: [])->toBe([])
        ->and($this->books->findActive($this->book->id)->coverKey)->toBeNull();
});

it('removes the cover: key set to null first, then the binary is deleted (data-model.md §7)', function () {
    $key = $this->upload->upload($this->book->id, PNG_SIGNATURE.'img')->coverKey;
    (new RemoveCoverService($this->books, $this->storage))->remove($this->book->id);

    expect($this->books->findActive($this->book->id)->coverKey)->toBeNull()
        ->and($this->storage->get($key))->toBeNull();
});

it('never resolves a key outside its folder', function () {
    expect($this->storage->get('../../etc/passwd'))->toBeNull()
        ->and($this->storage->get('unknown.png'))->toBeNull();
});
