<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\EventHook;

use PHPUnit\Event\Code\Phpt;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Core\Test\PHPUnit\Extension\EventHook\Subscriber\ResetEventHooksSubscriber;
use Shopware\Tests\Unit\Core\Test\PHPUnit\TelemetryInfoFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResetEventHooksSubscriber::class)]
class ResetEventHooksSubscriberTest extends TestCase
{
    public function testNotifyClearsTheHooksOfTheCurrentDispatcher(): void
    {
        $dispatcher = new EventHookDispatcher(new EventDispatcher());
        $dispatcher->on(Event::class, static function (): void {});

        (new ResetEventHooksSubscriber())->notify(new Finished(TelemetryInfoFactory::create(), new Phpt('fakeFile'), 0));

        static::assertFalse($dispatcher->hasListeners(Event::class));
    }
}
