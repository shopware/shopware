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
    public function testSmallMessageSyncTransportNoException(): void
    {
        $this->expectNotToPerformAssertions();

        $serializer = new Serializer();

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), true);

        $envelope = new Envelope(new \stdClass());

        $event = new SendMessageToTransportsEvent($envelope, ['test' => $this->createMock(SyncTransport::class)]);

        $listener($event);
    }

    public function testBigMessageSyncTransportNoException(): void
    {
        $this->expectNotToPerformAssertions();

        $serializer = new Serializer();

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), true);

        $message = new \stdClass();
        $message->a = str_repeat('a', 1024 * 256);
        $envelope = new Envelope($message);

        $event = new SendMessageToTransportsEvent($envelope, ['test' => $this->createMock(SyncTransport::class)]);

        $listener($event);
    }

    public function testSmallMessageAsyncTransportNoException(): void
    {
        $this->expectNotToPerformAssertions();

        $serializer = new Serializer();

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), true);

        $envelope = new Envelope(new \stdClass());

        $event = new SendMessageToTransportsEvent($envelope, []);

        $listener($event);
    }

    public function testBigMessageAsyncTransportException(): void
    {
        $serializer = new Serializer();

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), true);

        $message = new \stdClass();
        $message->a = str_repeat('a', 1024 * 256);
        $envelope = new Envelope($message);

        $event = new SendMessageToTransportsEvent($envelope, []);

        $this->expectExceptionObject(MessageQueueException::queueMessageSizeExceeded(\stdClass::class));

        $listener($event);
    }

    public function testSmallMessageAsyncTransportNoExceptionWithDisabledEnforceLimit(): void
    {
        $this->expectNotToPerformAssertions();

        $serializer = new Serializer();

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), false);

        $envelope = new Envelope(new \stdClass());

        $event = new SendMessageToTransportsEvent($envelope, []);

        $listener($event);
    }

    public function testBigMessageAsyncTransportNoExceptionWithDisabledEnforceLimit(): void
    {
        $this->expectNotToPerformAssertions();

        $serializer = new Serializer();

        $listener = new MessageQueueSizeRestrictListener(new MessageSizeCalculator($serializer), false);

        $message = new \stdClass();
        $message->a = str_repeat('a', 1024 * 256);
        $envelope = new Envelope($message);

        $event = new SendMessageToTransportsEvent($envelope, []);

        $listener($event);
    }
}
