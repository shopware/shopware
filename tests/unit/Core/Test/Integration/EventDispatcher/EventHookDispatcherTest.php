<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Integration\EventDispatcher;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * Integration test helpers are excluded from the coverage source, so this test covers nothing.
 */
#[Package('framework')]
#[CoversNothing]
class EventHookDispatcherTest extends TestCase
{
    public function testHooksRunAfterTheListenersOfTheInnerDispatcher(): void
    {
        $calls = [];
        $inner = new EventDispatcher();
        $inner->addListener(Event::class, static function () use (&$calls): void {
            $calls[] = 'listener';
        }, -1000);
        $dispatcher = new EventHookDispatcher($inner);
        $dispatcher->on(Event::class, static function () use (&$calls): void {
            $calls[] = 'hook';
        });

        $dispatcher->dispatch(new Event());

        static::assertSame(['listener', 'hook'], $calls);
    }

    public function testHooksReceiveTheEventAndItsName(): void
    {
        $received = [];
        $dispatcher = new EventHookDispatcher(new EventDispatcher());
        $dispatcher->on('custom.name', static function (object $event, string $eventName) use (&$received): void {
            $received = [$event, $eventName];
        });
        $event = new Event();

        $returned = $dispatcher->dispatch($event, 'custom.name');

        static::assertSame([$event, 'custom.name'], $received);
        static::assertSame($event, $returned);
    }

    public function testStoppedPropagationSkipsTheHooks(): void
    {
        $inner = new EventDispatcher();
        $inner->addListener(Event::class, static function (Event $event): void {
            $event->stopPropagation();
        });
        $dispatcher = new EventHookDispatcher($inner);
        $called = false;
        $dispatcher->on(Event::class, static function () use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch(new Event());

        static::assertFalse($called);
    }

    public function testOffRemovesOnlyTheGivenHook(): void
    {
        $calls = [];
        $dispatcher = new EventHookDispatcher(new EventDispatcher());
        $first = static function () use (&$calls): void {
            $calls[] = 'first';
        };
        $dispatcher->on(Event::class, $first);
        $dispatcher->on(Event::class, static function () use (&$calls): void {
            $calls[] = 'second';
        });

        $dispatcher->off(Event::class, $first);
        $dispatcher->dispatch(new Event());

        static::assertSame(['second'], $calls);
    }

    public function testOnceHookRunsForTheFirstDispatchOnly(): void
    {
        $calls = 0;
        $dispatcher = new EventHookDispatcher(new EventDispatcher());
        $dispatcher->on(Event::class, static function () use (&$calls): void {
            ++$calls;
        }, true);

        $dispatcher->dispatch(new Event());
        $dispatcher->dispatch(new Event());

        static::assertSame(1, $calls);
        static::assertFalse($dispatcher->hasListeners(Event::class));
    }

    public function testFromContainerReturnsTheRegisteredDispatcher(): void
    {
        $dispatcher = new EventHookDispatcher(new EventDispatcher());
        $container = new Container();
        $container->set(EventHookDispatcher::class, $dispatcher);

        static::assertSame($dispatcher, EventHookDispatcher::fromContainer($container));
    }

    public function testFromContainerRejectsAnotherService(): void
    {
        $container = new Container();
        $container->set(EventHookDispatcher::class, new \stdClass());

        $this->expectExceptionObject(new \LogicException(EventHookDispatcher::class . ' is only registered in the test environment.'));

        EventHookDispatcher::fromContainer($container);
    }

    public function testFromContainerRejectsAContainerWithoutTheDispatcher(): void
    {
        $this->expectExceptionObject(new \LogicException(EventHookDispatcher::class . ' is only registered in the test environment.'));

        EventHookDispatcher::fromContainer(new Container());
    }

    public function testResetCurrentClearsTheHooksOfTheLatestDispatcher(): void
    {
        $older = new EventHookDispatcher(new EventDispatcher());
        $older->on(Event::class, static function (): void {});
        $latest = new EventHookDispatcher(new EventDispatcher());
        $latest->on(Event::class, static function (): void {});

        EventHookDispatcher::resetCurrent();

        static::assertFalse($latest->hasListeners(Event::class));
        static::assertTrue($older->hasListeners(Event::class));
    }

    public function testResetClearsAllHooks(): void
    {
        $called = false;
        $dispatcher = new EventHookDispatcher(new EventDispatcher());
        $dispatcher->on(Event::class, static function () use (&$called): void {
            $called = true;
        });

        $dispatcher->reset();
        $dispatcher->dispatch(new Event());

        static::assertFalse($called);
        static::assertFalse($dispatcher->hasListeners(Event::class));
    }

    /**
     * @param array<string, string|array{0: string, 1?: int}|list<array{0: string, 1?: int}>> $subscribedEvents
     * @param list<string> $expectedCalls
     */
    #[DataProvider('subscribedEventsProvider')]
    public function testSubscribeHooksEveryMethodOfTheSubscriber(array $subscribedEvents, array $expectedCalls): void
    {
        $subscriber = new EventHookDispatcherTestSubscriber();
        EventHookDispatcherTestSubscriber::$subscribedEvents = $subscribedEvents;
        $dispatcher = new EventHookDispatcher(new EventDispatcher());

        $dispatcher->subscribe($subscriber);
        $dispatcher->dispatch(new Event());

        static::assertSame($expectedCalls, $subscriber->calls);
    }

    public static function subscribedEventsProvider(): \Generator
    {
        yield 'method name' => [[Event::class => 'first'], ['first']];
        yield 'method name with priority' => [[Event::class => ['first', 10]], ['first']];
        yield 'several methods' => [[Event::class => [['first', 10], ['second']]], ['first', 'second']];
    }

    public function testHasListenersReportsHookedEventsAndDelegatesOtherwise(): void
    {
        $inner = new EventDispatcher();
        $inner->addListener('listened', static function (): void {});
        $dispatcher = new EventHookDispatcher($inner);
        $dispatcher->on('hooked', static function (): void {});

        static::assertTrue($dispatcher->hasListeners('hooked'));
        static::assertTrue($dispatcher->hasListeners('listened'));
        static::assertFalse($dispatcher->hasListeners('neither'));
        static::assertTrue($dispatcher->hasListeners());
    }

    public function testListenerManagementIsForwardedToTheInnerDispatcher(): void
    {
        $inner = new EventDispatcher();
        $dispatcher = new EventHookDispatcher($inner);
        $listener = static function (): void {};
        $subscriber = new EventHookDispatcherTestSubscriber();
        EventHookDispatcherTestSubscriber::$subscribedEvents = ['subscribed' => 'first'];

        $dispatcher->addListener('listened', $listener, 5);
        $dispatcher->addSubscriber($subscriber);

        static::assertSame([$listener], $inner->getListeners('listened'));
        static::assertSame([$listener], $dispatcher->getListeners('listened'));
        static::assertSame(5, $dispatcher->getListenerPriority('listened', $listener));
        static::assertTrue($inner->hasListeners('subscribed'));

        $dispatcher->removeListener('listened', $listener);
        $dispatcher->removeSubscriber($subscriber);

        static::assertFalse($inner->hasListeners('listened'));
        static::assertFalse($inner->hasListeners('subscribed'));
    }
}

/**
 * @internal
 */
class EventHookDispatcherTestSubscriber implements EventSubscriberInterface
{
    /**
     * @var array<string, string|array{0: string, 1?: int}|list<array{0: string, 1?: int}>>
     */
    public static array $subscribedEvents = [];

    /**
     * @var list<string>
     */
    public array $calls = [];

    public static function getSubscribedEvents(): array
    {
        return self::$subscribedEvents;
    }

    public function first(): void
    {
        $this->calls[] = 'first';
    }

    public function second(): void
    {
        $this->calls[] = 'second';
    }
}
