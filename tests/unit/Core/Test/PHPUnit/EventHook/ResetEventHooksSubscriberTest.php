<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\EventHook;

use PHPUnit\Event\Code\Phpt;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Core\Test\PHPUnit\EventHook\ResetEventHooksSubscriber;
use Shopware\Tests\Unit\Core\Test\PHPUnit\TelemetryInfoFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * The subscriber is excluded from the coverage source, so this test covers nothing.
 */
#[Package('framework')]
#[CoversNothing]
class ResetEventHooksSubscriberTest extends TestCase
{
    public function testNotifyClearsTheHooksOfTheCurrentDispatcher(): void
    {
        $dispatcher = new EventHookDispatcher(new EventDispatcher());
        $dispatcher->on(Event::class, static function (): void {});

        (new ResetEventHooksSubscriber())->notify(new PreparationStarted(TelemetryInfoFactory::create(), new Phpt('fakeFile')));

        static::assertFalse($dispatcher->hasListeners(Event::class));
    }
}
