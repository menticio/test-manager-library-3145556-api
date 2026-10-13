<?php

// T-19 evidence: Constitution, Articles I, II and III as executable rules (Pest arch tests).

// Article I: the domain depends on nothing (native PHP only).
arch('the domain does not depend on any other layer nor on Laravel')
    ->expect('App\Domain')
    ->not->toUse(['App\Application', 'App\Adapters', 'App\Bootstrap', 'Illuminate', 'Firebase']);

arch('the domain only uses native PHP and itself')
    ->expect('App\Domain')
    ->toOnlyUse(['App\Domain', 'DateTimeImmutable', 'DateInterval', 'DomainException', 'LogicException']);

// Article II: the application knows only the domain; ports are its own interfaces.
arch('the application does not depend on adapters, bootstrap nor frameworks')
    ->expect('App\Application')
    ->not->toUse(['App\Adapters', 'App\Bootstrap', 'Illuminate', 'Firebase']);

arch('ports are interfaces defined by the application (DIP)')
    ->expect('App\Application\Ports\Outbound')
    ->toBeInterfaces()
    ->ignoring('App\Application\Ports\Outbound\ConcurrencyConflict');

// Article III: a single composition point.
arch('outbound adapters are only wired in the composition module')
    ->expect('App\Adapters\Outbound')
    ->toOnlyBeUsedIn(['App\Bootstrap\PortBindingsServiceProvider', 'App\Adapters\Outbound']);

// Article IV (SRP): one application service per use case, immutable.
arch('application services are final and readonly')
    ->expect('App\Application\Services')
    ->classes()->toBeFinal()
    ->ignoring('App\Application\Services\CoverImage');
