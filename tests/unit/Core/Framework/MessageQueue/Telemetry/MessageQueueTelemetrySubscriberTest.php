<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Service\MessageSizeCalculator;
use Shopware\Core\Framework\MessageQueue\Telemetry\MessageGroupResolver;
use Shopware\Core\Framework\MessageQueue\Telemetry\MessageQueueTelemetrySubscriber;
use Shopware\Core\Framework\MessageQueue\Telemetry\WorkerMessageTimingHelper;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Test\Stub\Telemetry\CollectingMeter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MessageQueueTelemetrySubscriber::class)]
class MessageQueueTelemetrySubscriberTest extends TestCase
{
    private CollectingMeter $meter;

    public function testSubscribesToWorkerEvents(): void
    {
        static::assertSame(
            [
                WorkerMessageReceivedEvent::class => 'onMessageReceived',
                WorkerMessageHandledEvent::class => 'onMessageHandled',
                WorkerMessageFailedEvent::class => 'onMessageFailed',
            ],
            MessageQueueTelemetrySubscriber::getSubscribedEvents()
        );
    }

    public function testOnMessageReceivedEmitsSize(): void
    {
        $subscriber = $this->createSubscriber(messageSize: 15);

        $subscriber->onMessageReceived(new WorkerMessageReceivedEvent(new Envelope(new \stdClass()), 'async'));

        $size = $this->meter->getMetric('messenger.message.size');
        static::assertSame(15, $size->value);
        // check for no outcome metrics yet
        static::assertNull($this->findMetric('messenger.message.handled.count'));
        static::assertNull($this->findMetric('messenger.message.handling.duration'));
    }

    public function testEmitsHandledCountAndDuration(): void
    {
        $subscriber = $this->createSubscriber();
        $envelope = new Envelope(new \stdClass());

        $subscriber->onMessageReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
        $subscriber->onMessageHandled(new WorkerMessageHandledEvent($envelope, 'async'));

        $count = $this->meter->getMetric('messenger.message.handled.count');
        static::assertSame(1, $count->value);
        static::assertSame(['message_group' => 'other', 'result' => 'handled'], $count->labels);

        $duration = $this->meter->getMetric('messenger.message.handling.duration');
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
        static::assertSame(['message_group' => 'other', 'result' => 'handled'], $duration->labels);
    }

    public function testFailedWithoutRetryEmitsFailedResultAndDuration(): void
    {
        $subscriber = $this->createSubscriber();
        $envelope = new Envelope(new \stdClass());

        $subscriber->onMessageReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
        $subscriber->onMessageFailed(new WorkerMessageFailedEvent($envelope, 'async', new \RuntimeException()));

        $labels = $this->meter->getLabels('messenger.message.handled.count');
        static::assertSame('failed', $labels['result']);
        // failure latency is a first-class signal (slow failures burn worker slots); the result label
        // keeps it separable from success latency
        static::assertSame(
            ['message_group' => 'other', 'result' => 'failed'],
            $this->meter->getMetric('messenger.message.handling.duration')->labels
        );
    }

    public function testFailedWithRetryEmitsRetriedResult(): void
    {
        $subscriber = $this->createSubscriber();
        $envelope = new Envelope(new \stdClass());

        $subscriber->onMessageReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
        $failed = new WorkerMessageFailedEvent($envelope, 'async', new \RuntimeException());
        $failed->setForRetry();
        $subscriber->onMessageFailed($failed);

        $labels = $this->meter->getLabels('messenger.message.handled.count');
        static::assertSame('retried', $labels['result']);
        $labels = $this->meter->getLabels('messenger.message.handling.duration');
        static::assertSame('retried', $labels['result']);
    }

    public function testDurationSkippedWhenReceiveNotRecorded(): void
    {
        $subscriber = $this->createSubscriber();

        // handled without a preceding received event - no information about timing
        $subscriber->onMessageHandled(new WorkerMessageHandledEvent(new Envelope(new \stdClass()), 'async'));

        static::assertNotNull($this->findMetric('messenger.message.handled.count'));
        static::assertNull($this->findMetric('messenger.message.handling.duration'));
    }

    private function createSubscriber(int $messageSize = 0): MessageQueueTelemetrySubscriber
    {
        $this->meter = new CollectingMeter();

        $sizeCalculator = static::createStub(MessageSizeCalculator::class);
        $sizeCalculator->method('size')->willReturn($messageSize);

        return new MessageQueueTelemetrySubscriber(
            $this->meter,
            $sizeCalculator,
            new MessageGroupResolver(),
            new WorkerMessageTimingHelper()
        );
    }

    private function findMetric(string $name): ?ConfiguredMetric
    {
        foreach ($this->meter->getMetrics() as $metric) {
            if ($metric->name === $name) {
                return $metric;
            }
        }

        return null;
    }
}
