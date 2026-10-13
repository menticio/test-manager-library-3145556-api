<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Input;
use App\Adapters\Inbound\Http\Present;
use App\Application\Services\CreateBookService;
use App\Application\Services\GetBookService;
use App\Application\Services\GetCoverService;
use App\Application\Services\ListCategoriesService;
use App\Application\Services\RemoveCoverService;
use App\Application\Services\RetireBookService;
use App\Application\Services\SearchBooksService;
use App\Application\Services\UpdateBookService;
use App\Application\Services\UploadCoverService;
use App\Domain\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** §3.3 categories and §3.4 books and covers. */
final class BookController
{
    public function __construct(private readonly ListCategoriesService $categories) {}

    public function categories(): JsonResponse
    {
        return new JsonResponse(['items' => $this->categories->list()]);
    }

    public function search(Request $request, SearchBooksService $service): JsonResponse
    {
        $page = $service->search(Input::optionalQuery($request, 'q'), Input::optionalQuery($request, 'category_id'),
            Input::optionalQueryInt($request, 'page'), Input::optionalQueryInt($request, 'size'));
        $names = $this->names();

        return new JsonResponse(Present::page($page, fn (Book $book) => Present::book($book, $names)));
    }

    public function show(string $id, GetBookService $service): JsonResponse
    {
        return $this->book($service->get($id));
    }

    public function store(Request $request, CreateBookService $service): JsonResponse
    {
        $book = $service->create(Input::string($request, 'title'), Input::string($request, 'author'), Input::string($request, 'isbn'),
            Input::string($request, 'category_id'), Input::int($request, 'total_copies'));

        return $this->book($book, 201);
    }

    public function update(string $id, Request $request, UpdateBookService $service): JsonResponse
    {
        return $this->book($service->update($id, Input::string($request, 'title'), Input::string($request, 'author'),
            Input::string($request, 'category_id'), Input::int($request, 'total_copies')));
    }

    public function destroy(string $id, RetireBookService $service): Response
    {
        $service->retire($id);

        return new Response(null, 204);
    }

    public function putCover(string $id, Request $request, UploadCoverService $service): JsonResponse
    {
        return $this->book($service->upload($id, Input::uploadedFile($request)));
    }

    public function deleteCover(string $id, RemoveCoverService $service): Response
    {
        $service->remove($id);

        return new Response(null, 204);
    }

    public function cover(string $id, GetCoverService $service): Response
    {
        $cover = $service->get($id);

        return new Response($cover['bytes'], 200, ['Content-Type' => $cover['content_type']]);
    }

    private function book(Book $book, int $status = 200): JsonResponse
    {
        return new JsonResponse(Present::book($book, $this->names()), $status, [], JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, string> */
    private function names(): array
    {
        return array_column($this->categories->list(), 'name', 'id');
    }
}
