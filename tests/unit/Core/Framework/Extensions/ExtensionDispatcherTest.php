<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Extensions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ExtensionDispatcher::class)]
class ExtensionDispatcherTest extends TestCase
{
    public function testPublishesEventsSuccessfully(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatched = [];
        $this->recordLifecycleEvents($dispatcher, $dispatched);

        $extension = new class extends Extension {
            public const NAME = 'test.extension';

            public function getParams(): array
            {
                return ['param1' => 'value1', 'param2' => 2];
            }
        };

        $function = static fn (string $param1, int $param2) => $param1 . $param2;

        $extensionDispatcher = new ExtensionDispatcher($dispatcher);
        $result = $extensionDispatcher->publish('eventName', $extension, $function);

        static::assertSame('value12', $result);
        static::assertSame(['eventName.pre', 'eventName.post'], $dispatched);
    }

    public function testCallsFunctionWithoutDispatchingWhenNothingListens(): void
    {
        $dispatcher = new CollectingEventDispatcher();
        $extension = new class extends Extension {
            public const NAME = 'test.extension';

            public function getParams(): array
            {
                return ['param1' => 'value1', 'param2' => 2];
            }
        };

        $function = static fn (string $param1, int $param2) => $param1 . $param2;

        $extensionDispatcher = new ExtensionDispatcher($dispatcher);
        $result = $extensionDispatcher->publish('eventName', $extension, $function);

        static::assertSame('value12', $result);
        static::assertSame([], $dispatcher->getEvents());
    }

    public function testPreListenerCanReplaceTheResult(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('eventName.pre', static function (Extension $extension): void {
            $extension->result = 'replacedResult';
            $extension->stopPropagation();
        });

        $extension = new class extends Extension {
            public const NAME = 'test.extension';

            public function getParams(): array
            {
                return [];
            }
        };

        $called = false;
        $function = static function () use (&$called): string {
            $called = true;

            return 'originalResult';
        };

        $extensionDispatcher = new ExtensionDispatcher($dispatcher);
        $result = $extensionDispatcher->publish('eventName', $extension, $function);

        static::assertSame('replacedResult', $result);
        static::assertFalse($called);
    }

    public function testDetectsListenersForEveryLifecycleEvent(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventNames = [];
        $dispatcher->expects($this->exactly(3))
            ->method('hasListeners')
            ->with(static::callback(static function (string $eventName) use (&$eventNames): bool {
                $eventNames[] = $eventName;

                return true;
            }))
            ->willReturnOnConsecutiveCalls(false, false, true);

        $extensionDispatcher = new ExtensionDispatcher($dispatcher);

        static::assertTrue($extensionDispatcher->hasListeners('test.extension'));
        static::assertSame([
            'test.extension.pre',
            'test.extension.post',
            'test.extension.error',
        ], $eventNames);
    }

    public function testHandlesExceptionGracefully(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatched = [];
        $this->recordLifecycleEvents($dispatcher, $dispatched);
        $dispatcher->addListener('eventName.error', static function (Extension $extension): void {
            $extension->result = 'handledResult';
        });

        $extension = new class extends Extension {
            public const NAME = 'test.extension';

            public function getParams(): array
            {
                return [];
            }
        };

        $function = static fn () => throw new \Exception('Test exception');

        $extensionDispatcher = new ExtensionDispatcher($dispatcher);
        $result = $extensionDispatcher->publish('eventName', $extension, $function);

        static::assertSame('handledResult', $result);
        static::assertSame(['eventName.pre', 'eventName.error', 'eventName.post'], $dispatched);
    }

    public function testRethrowsExceptionWhenNoResult(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatched = [];
        $this->recordLifecycleEvents($dispatcher, $dispatched);

        $extension = new class extends Extension {
            public const NAME = 'test.extension';

            public function getParams(): array
            {
                return [];
            }
        };

        $function = static fn () => throw new \Exception('Test exception');

        $extensionDispatcher = new ExtensionDispatcher($dispatcher);

        $this->expectExceptionObject(new \Exception('Test exception'));

        try {
            $extensionDispatcher->publish('eventName', $extension, $function);
        } finally {
            static::assertSame(['eventName.pre', 'eventName.error'], $dispatched);
        }
    }

    public function testRethrowsExceptionWithoutDispatchingWhenNothingListens(): void
    {
        $dispatcher = new CollectingEventDispatcher();
        $extension = new class extends Extension {
            public const NAME = 'test.extension';

            public function getParams(): array
            {
                return [];
            }
        };

        $function = static fn () => throw new \Exception('Test exception');

        $extensionDispatcher = new ExtensionDispatcher($dispatcher);

        $this->expectExceptionObject(new \Exception('Test exception'));

        try {
            $extensionDispatcher->publish('eventName', $extension, $function);
        } finally {
            static::assertSame([], $dispatcher->getEvents());
        }
    }

    /**
     * @param list<string> $dispatched
     *
     * @param-out list<string> $dispatched
     */
    private function recordLifecycleEvents(EventDispatcher $dispatcher, array &$dispatched): void
    {
        foreach (['eventName.pre', 'eventName.post', 'eventName.error'] as $lifecycleEvent) {
            $dispatcher->addListener($lifecycleEvent, static function (Extension $extension, string $eventName) use (&$dispatched): void {
                $dispatched[] = $eventName;
            });
        }
    }
}
