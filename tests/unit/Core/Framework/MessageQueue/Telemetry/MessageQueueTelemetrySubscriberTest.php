<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\Service\MessageSizeCalculator;
use Shopware\Core\Framework\MessageQueue\Telemetry\MessageQueueTelemetrySubscriber;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;

/**
 * @internal
 */
#[CoversClass(MessageQueueTelemetrySubscriber::class)]
class MessageQueueTelemetrySubscriberTest extends TestCase
{
    private MessageQueueTelemetrySubscriber $subscriber;

    private Meter&MockObject $meter;

    private MessageSizeCalculator&MockObject $messageSizeCalculator;

    protected function setUp(): void
    {
        $this->meter = $this->createMock(Meter::class);
        $this->messageSizeCalculator = $this->createMock(MessageSizeCalculator::class);
        $this->subscriber = new MessageQueueTelemetrySubscriber($this->meter, $this->messageSizeCalculator);
    }

    public function testOnMessageReceived(): void
    {
        $serializedMessage = 'test message';
        $stamp = new SerializedMessageStamp($serializedMessage);

        $envelope = new WorkerMessageReceivedEvent(
            new Envelope(new \stdClass(), [$stamp]),
            'test'
        );

        $this->messageSizeCalculator->expects(static::once())
            ->method('size')
            ->with($envelope->getEnvelope())
            ->willReturn(15);

        $this->meter->expects(static::once())
            ->method('emit')
            ->with(static::callback(function (ConfiguredMetric $histogram) {
                return $histogram->name === 'messenger.message.size'
                    && $histogram->value === 15;
            }));

        $this->subscriber->emitMessageSizeMetric($envelope);
    }
}
