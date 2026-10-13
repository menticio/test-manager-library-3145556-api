<?php

namespace Tests\Fakes;

use App\Application\Page;
use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\Member;

final class InMemoryMemberRepository implements MemberRepository
{
    /** @var array<string, Member> */
    public array $members = [];

    public function find(string $id): ?Member
    {
        return $this->members[$id] ?? null;
    }

    public function documentExists(string $documentNumber): bool
    {
        foreach ($this->members as $member) {
            if ($member->documentNumber->value === $documentNumber) {
                return true;
            }
        }

        return false;
    }

    public function add(Member $member): void
    {
        $this->members[$member->id] = $member;
    }

    public function search(?string $text, int $page, int $size): Page
    {
        $matches = array_values(array_filter($this->members, fn (Member $m) => $text === null
            || mb_stripos($m->fullName, $text) !== false || mb_stripos($m->documentNumber->value, $text) !== false));
        usort($matches, fn (Member $a, Member $b) => strcmp($a->fullName, $b->fullName));

        return new Page(array_slice($matches, ($page - 1) * $size, $size), $page, $size, count($matches));
    }
}
