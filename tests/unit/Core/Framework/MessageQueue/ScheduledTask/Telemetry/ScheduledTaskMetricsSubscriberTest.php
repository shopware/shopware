<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTask;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry\ScheduledTaskMetricsSubscriber;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry\TaskNameResolver;
use Shopware\Core\Framework\MessageQueue\Telemetry\WorkerMessageTimingHelper;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScheduledTaskMetricsSubscriber::class)]
class ScheduledTaskMetricsSubscriberTest extends TestCase
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

    protected function setUp(): void
    {
        $this->emitted = [];
    }

    public function testSubscribesToWorkerEvents(): void
    {
        static::assertSame(
            [
                WorkerMessageReceivedEvent::class => 'onMessageReceived',
                WorkerMessageHandledEvent::class => 'onMessageHandled',
                WorkerMessageFailedEvent::class => 'onMessageFailed',
            ],
            ScheduledTaskMetricsSubscriber::getSubscribedEvents()
        );
    }

    public function testHandledEmitsSuccessDuration(): void
    {
        $subscriber = $this->createSubscriber();
        $envelope = new Envelope(new InvalidateCacheTask());

        $subscriber->onMessageReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
        $subscriber->onMessageHandled(new WorkerMessageHandledEvent($envelope, 'async'));

        $duration = $this->findMetric('scheduled_task.run.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertSame(
            ['task_name' => 'task_name_label:shopware.invalidate_cache', 'result' => 'success'],
            $duration->labels
        );
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
    }

    public function testUnknownTaskEmitsSuccessDuration(): void
    {
        $subscriber = $this->createSubscriber();
        $envelope = new Envelope(new ScheduledTaskMetricsSubscriberTestTask());

        $subscriber->onMessageReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
        $subscriber->onMessageHandled(new WorkerMessageHandledEvent($envelope, 'async'));

        $duration = $this->findMetric('scheduled_task.run.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertSame(
            ['task_name' => 'task_name_label:my_plugin.custom_task', 'result' => 'success'],
            $duration->labels
        );
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
    }

    public function testFailedWithoutRetryEmitsFailedResult(): void
    {
        $subscriber = $this->createSubscriber();
        $envelope = new Envelope(new InvalidateCacheTask());

        $subscriber->onMessageReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
        $subscriber->onMessageFailed(new WorkerMessageFailedEvent($envelope, 'async', new \RuntimeException()));

        $duration = $this->findMetric('scheduled_task.run.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertSame(
            ['task_name' => 'task_name_label:shopware.invalidate_cache', 'result' => 'failed'],
            $duration->labels
        );
    }

    public function testFailedWithRetryEmitsNothing(): void
    {
        $subscriber = $this->createSubscriber();
        $envelope = new Envelope(new InvalidateCacheTask());

        $subscriber->onMessageReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
        $failed = new WorkerMessageFailedEvent($envelope, 'async', new \RuntimeException());
        $failed->setForRetry();
        $subscriber->onMessageFailed($failed);

        static::assertNull($this->findMetric('scheduled_task.run.duration'));
    }

    public function testHandledWithoutReceiveEmitsNothing(): void
    {
        $subscriber = $this->createSubscriber();

        // handled without a preceding received event - no recorded start, no timing
        $subscriber->onMessageHandled(new WorkerMessageHandledEvent(new Envelope(new InvalidateCacheTask()), 'async'));

        static::assertNull($this->findMetric('scheduled_task.run.duration'));
    }

    public function testOrdinaryMessageEmitsNothing(): void
    {
        $subscriber = $this->createSubscriber();
        $envelope = new Envelope(new \stdClass());

        $subscriber->onMessageReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
        $subscriber->onMessageHandled(new WorkerMessageHandledEvent($envelope, 'async'));

        static::assertNull($this->findMetric('scheduled_task.run.duration'));
    }

    private function createSubscriber(): ScheduledTaskMetricsSubscriber
    {
        $meter = static::createStub(Meter::class);
        $meter->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });

        $taskNameResolver = static::createStub(TaskNameResolver::class);
        $taskNameResolver->method('resolve')->willReturnCallback(
            static fn (string $taskName): string => 'task_name_label:' . $taskName
        );

        return new ScheduledTaskMetricsSubscriber(
            $meter,
            $taskNameResolver,
            new WorkerMessageTimingHelper()
        );
    }

    private function findMetric(string $name): ?ConfiguredMetric
    {
        foreach ($this->emitted as $metric) {
            if ($metric->name === $name) {
                return $metric;
            }
        }

        return null;
    }
}

/**
 * @internal
 */
class ScheduledTaskMetricsSubscriberTestTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'my_plugin.custom_task';
    }

    public static function getDefaultInterval(): int
    {
        return 60;
    }
}
