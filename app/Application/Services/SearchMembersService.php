<?php

namespace App\Application\Services;

use App\Application\Page;
use App\Application\Ports\Outbound\MemberRepository;

/** CA-05.2: list and search members by name or document, paginated. */
final readonly class SearchMembersService
{
    public function __construct(private MemberRepository $members) {}

    public function search(?string $text, ?int $page, ?int $size): Page
    {
        [$page, $size] = Page::validate($page, $size);
        $text = $text === null ? null : trim($text);

        return $this->members->search($text === '' ? null : $text, $page, $size);
    }
}
