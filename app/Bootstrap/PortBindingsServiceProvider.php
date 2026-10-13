<?php

namespace App\Bootstrap;

use App\Adapters\Outbound\Clock\SystemClock;
use App\Adapters\Outbound\Persistence\PgBookRepository;
use App\Adapters\Outbound\Persistence\PgCategoryRepository;
use App\Adapters\Outbound\Persistence\PgLoanRepository;
use App\Adapters\Outbound\Persistence\PgMemberRepository;
use App\Adapters\Outbound\Persistence\PgOverdueReader;
use App\Adapters\Outbound\Persistence\PgReportReader;
use App\Adapters\Outbound\Persistence\PgUserRepository;
use App\Adapters\Outbound\Security\Argon2idPasswordHasher;
use App\Adapters\Outbound\Security\JwtTokenIssuer;
use App\Adapters\Outbound\Storage\DiskCoverStorage;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\CoverStorage;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Ports\Outbound\MemberRepository;
use App\Application\Ports\Outbound\OverdueReader;
use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\ReportReader;
use App\Application\Ports\Outbound\TokenIssuer;
use App\Application\Ports\Outbound\UserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Constitution, Article III: the ONLY place where a port meets its adapter.
 * Changing an adapter (disk -> S3, another engine) is one line here (Article IV, OCP).
 */
final class PortBindingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, fn () => new SystemClock);

        $this->app->bind(UserRepository::class, fn () => new PgUserRepository);
        $this->app->bind(BookRepository::class, fn () => new PgBookRepository);
        $this->app->bind(CategoryRepository::class, fn () => new PgCategoryRepository);
        $this->app->bind(MemberRepository::class, fn () => new PgMemberRepository);
        $this->app->bind(LoanRepository::class, fn () => new PgLoanRepository);
        $this->app->bind(OverdueReader::class, fn () => new PgOverdueReader);
        $this->app->bind(ReportReader::class, fn () => new PgReportReader);

        $this->app->bind(PasswordHasher::class, fn () => new Argon2idPasswordHasher);
        $this->app->bind(TokenIssuer::class, fn ($app) => new JwtTokenIssuer((string) config('library.jwt_secret'), $app->make(Clock::class)));

        $this->app->bind(CoverStorage::class, function () {
            $dir = (string) config('library.covers_dir');

            return new DiskCoverStorage(str_starts_with($dir, '/') ? $dir : base_path($dir));
        });
    }
}
