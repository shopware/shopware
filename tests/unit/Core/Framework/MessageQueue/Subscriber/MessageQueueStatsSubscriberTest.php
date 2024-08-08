<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\MessageQueue\Subscriber\MessageQueueStatsSubscriber;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Histogram;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;

/**
 * @internal
 */
#[CoversClass(MessageQueueStatsSubscriber::class)]
class MessageQueueStatsSubscriberTest extends TestCase
{
    private MessageQueueStatsSubscriber $subscriber;

    private Meter&MockObject $meter;

    protected function setUp(): void
    {
        $this->meter = $this->createMock(Meter::class);
        $this->subscriber = new MessageQueueStatsSubscriber($this->createMock(IncrementGatewayRegistry::class), $this->meter);
    }

    public function testOnMessageReceived(): void
    {
        $serializedMessage = 'test message';
        $stamp = new SerializedMessageStamp($serializedMessage);

        $envelope = new WorkerMessageReceivedEvent(
            new Envelope(new \stdClass(), [$stamp]),
            'test'
        );

        $this->meter->expects(static::once())
            ->method('emit')
            ->with(static::callback(function (Histogram $histogram) use ($serializedMessage) {
                return $histogram->name === 'messenger.message.size'
                    && $histogram->value === \strlen($serializedMessage)
                    && $histogram->description === 'Size of the message in bytes';
            }));

        $this->subscriber->onMessageReceived($envelope);
    }
}
