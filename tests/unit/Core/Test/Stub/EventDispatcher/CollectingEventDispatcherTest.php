<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Stub\EventDispatcher;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CollectingEventDispatcher::class)]
class CollectingEventDispatcherTest extends TestCase
{
    public function testListeners(): void
    {
        $dispatcher = new CollectingEventDispatcher();

        static::assertEmpty($dispatcher->getListeners());

        $callable = function (): void {};

        $dispatcher->addListener('event.name', $callable, 10);
        static::assertSame(['10' => [$callable]], $dispatcher->getListeners('event.name'));
        static::assertSame(['event.name' => ['10' => [$callable]]], $dispatcher->getListeners());
    }

    public function testDispatchingEvents(): void
    {
        $dispatcher = new CollectingEventDispatcher();

        $event1 = new class {};
        $event2 = new class {};

        $dispatcher->dispatch($event1, 'event.one');
        $dispatcher->dispatch($event2);

        $events = $dispatcher->getEvents();
        static::assertCount(2, $events);
        static::assertSame($event1, $events['event.one']);
        static::assertSame($event2, $events[0]);
    }
}
