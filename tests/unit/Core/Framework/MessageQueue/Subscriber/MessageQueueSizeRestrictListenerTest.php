<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\MessageQueue\MessageQueueException;
use Shopware\Core\Framework\MessageQueue\Service\MessageSizeCalculator;
use Shopware\Core\Framework\MessageQueue\Subscriber\MessageQueueSizeRestrictListener;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

/**
 * @internal
 */
#[CoversClass(MessageQueueSizeRestrictListener::class)]
class MessageQueueSizeRestrictListenerTest extends TestCase
{
    public function testSyncTransportDoesNothing(): void
    {
        $serializer = new Serializer();
        $logger = $this->createMock(LoggerInterface::class);

        $logger
            ->expects(static::never())
            ->method('critical');

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), $logger, false);

        $envelope = new Envelope(new \stdClass());

        $event = new SendMessageToTransportsEvent($envelope, ['test' => $this->createMock(SyncTransport::class)]);

        $listener($event);
    }

    public function testSmallMessageDoesNotLock(): void
    {
        $serializer = new Serializer();
        $logger = $this->createMock(LoggerInterface::class);

        $logger
            ->expects(static::never())
            ->method('critical');

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), $logger, false);

        $envelope = new Envelope(new \stdClass());

        $event = new SendMessageToTransportsEvent($envelope, []);

        $listener($event);
    }

    public function testBigMessageLogsInsteadOfException(): void
    {
        $serializer = new Serializer();
        $logger = $this->createMock(LoggerInterface::class);

        $logger
            ->expects(static::once())
            ->method('critical');

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), $logger, false);

        $stdClass = new \stdClass();
        $stdClass->a = str_repeat('a', 1024 * 256);
        $envelope = new Envelope($stdClass);

        $event = new SendMessageToTransportsEvent($envelope, []);

        $listener($event);
    }

    public function testBigMessageThrowsException(): void
    {
        $serializer = new Serializer();
        $logger = $this->createMock(LoggerInterface::class);

        $logger
            ->expects(static::never())
            ->method('critical');

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), $logger, true);

        $stdClass = new \stdClass();
        $stdClass->a = str_repeat('a', 1024 * 256);
        $envelope = new Envelope($stdClass);

        $event = new SendMessageToTransportsEvent($envelope, []);

        $this->expectExceptionObject(MessageQueueException::queueMessageSizeExceeded(\stdClass::class));

        $listener($event);
    }
}
