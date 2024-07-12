<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Messenger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Framework\Adapter\Messenger\MessageBus;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\MessageQueue\LowPriorityMessageInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(MessageBus::class)]
class MessageBusTest extends TestCase
{
    /**
     * @param array<string, string|array<string>> $config
     * @param array<StampInterface> $providedStamps
     * @param array<StampInterface> $expectedStamps
     */
    #[DataProvider('dispatchProvider')]
    public function testDispatch(object $message, array $config, array $providedStamps, array $expectedStamps): void
    {
        if (Feature::isActive('v6.7.0.0')) {
            $bus = new MessageBus(new Collector(), [], $config);
        } else {
            $bus = new MessageBus(new Collector(), $config, []);
        }

        $envelope = $bus->dispatch($message, $providedStamps);

        static::assertEquals(new Envelope($message, $expectedStamps), $envelope);
    }

    public function testOverwrite(): void
    {
        Feature::skipTestIfInActive('v6.7.0.0', $this);

        $bus = new MessageBus(new Collector(), [], [
            EntityIndexingMessage::class => 'low_priority',
        ]);

        $message = new ProductIndexingMessage([]);

        $envelope = $bus->dispatch($message);

        static::assertEquals(new Envelope($message, [new TransportNamesStamp('low_priority')]), $envelope);
    }

    public static function dispatchProvider(): \Generator
    {
        yield 'No config, no stamps' => [
            'message' => new AsyncMessage(),
            'config' => [],
            'providedStamps' => [],
            'expectedStamps' => [],
        ];

        yield 'Default config, no stamps' => [
            'message' => new AsyncMessage(),
            'config' => [
                AsyncMessageInterface::class => 'async',
                LowPriorityMessageInterface::class => 'low_priority',
                SendEmailMessage::class => 'async',
            ],
            'providedStamps' => [],
            'expectedStamps' => [],
        ];

        yield 'Explicit config, single transport, get stamped' => [
            'message' => new AsyncMessage(),
            'config' => [
                AsyncMessage::class => 'async',
                AsyncMessageInterface::class => 'async',
                LowPriorityMessageInterface::class => 'low_priority',
                SendEmailMessage::class => 'async',
            ],
            'providedStamps' => [],
            'expectedStamps' => [
                new TransportNamesStamp(['async']),
            ],
        ];

        yield 'Explicit config, multiple transports, get stamped' => [
            'message' => new AsyncMessage(),
            'config' => [
                AsyncMessage::class => ['async', 'low_priority'],
                AsyncMessageInterface::class => 'async',
                LowPriorityMessageInterface::class => 'low_priority',
                SendEmailMessage::class => 'async',
            ],
            'providedStamps' => [],
            'expectedStamps' => [
                new TransportNamesStamp(['async', 'low_priority']),
            ],
        ];

        yield 'Pre-stamped message, no config, direct dispatch' => [
            'message' => new AsyncMessage(),
            'config' => [],
            'providedStamps' => [
                new TransportNamesStamp(['async', 'low_priority']),
            ],
            'expectedStamps' => [
                new TransportNamesStamp(['async', 'low_priority']),
            ],
        ];
    }
}

/**
 * @internal
 */
class AsyncMessage
{
}

/**
 * @internal
 */
class Collector implements MessageBusInterface
{
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        return new Envelope($message, $stamps);
    }
}
