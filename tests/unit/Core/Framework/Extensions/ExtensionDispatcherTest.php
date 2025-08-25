<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Extensions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ExtensionDispatcher::class)]
class ExtensionDispatcherTest extends TestCase
{
    public function testPublishesEventsSuccessfully(): void
    {
        $dispatcher = new CollectingEventDispatcher();
        $extension = new class extends Extension {
            public const NAME = 'test.extension';

            public function getParams(): array
            {
                return ['param1' => 'value1', 'param2' => 2];
            }
        };

        $function = fn (string $param1, int $param2) => $param1 . $param2;

        $extensionDispatcher = new ExtensionDispatcher($dispatcher);
        $result = $extensionDispatcher->publish('eventName', $extension, $function);

        static::assertSame('value12', $result);

        static::assertSame([
            'eventName.pre' => $extension,
            'eventName.post' => $extension,
        ], $dispatcher->getEvents());
    }

    public function testHandlesExceptionGracefully(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $extension = new class extends Extension {
            public const NAME = 'test.extension';

            public function getParams(): array
            {
                return [];
            }
        };

        $dispatcher->expects($this->exactly(3))->method('dispatch')->with(
            $extension,
            static::callback(function ($eventName) use ($extension) {
                if ($eventName === 'eventName.error') {
                    $extension->result = 'handledResult'; // Simulate graceful handling of the exception
                }

                return \in_array($eventName, ['eventName.pre', 'eventName.post', 'eventName.error'], true);
            }),
        );

        $function = fn () => throw new \Exception('Test exception');

        $extensionDispatcher = new ExtensionDispatcher($dispatcher);
        $result = $extensionDispatcher->publish('eventName', $extension, $function);

        static::assertSame('handledResult', $result);
    }

    public function testRethrowsExceptionWhenNoResult(): void
    {
        $dispatcher = new CollectingEventDispatcher();
        $extension = new class extends Extension {
            public const NAME = 'test.extension';

            public function getParams(): array
            {
                return [];
            }
        };

        $function = fn () => throw new \Exception('Test exception');

        $extensionDispatcher = new ExtensionDispatcher($dispatcher);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        try {
            $extensionDispatcher->publish('eventName', $extension, $function);
        } finally {
            static::assertSame([
                'eventName.pre' => $extension,
                'eventName.error' => $extension,
            ], $dispatcher->getEvents());
        }
    }
}
