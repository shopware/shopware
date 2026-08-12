<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\Telemetry\WorkerMessageTimingHelper;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * Emits `scheduled_task.run.duration` for worker messages that are scheduled tasks, with `task_name`
 * and `result` labels (`success` on handled, `failed` on failed-without-retry; retried attempts are
 * covered by {@see \Shopware\Core\Framework\MessageQueue\Telemetry\MessageQueueTelemetrySubscriber}).
 *
 * Kept separate from message stats collector so the per-task duration is not lost in overall queue noise.
 *
 * Tagged `shopware.telemetry.subscriber`, so `TelemetrySubscriberCompilerPass` removes the service
 * when telemetry is disabled.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class ScheduledTaskMetricsSubscriber implements EventSubscriberInterface
{
    private const RESULT_SUCCESS = 'success';
    private const RESULT_FAILED = 'failed';

    public function __construct(
        private readonly Meter $meter,
        private readonly TaskNameResolver $taskNameResolver,
        private readonly WorkerMessageTimingHelper $timingHelper,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onMessageReceived',
            WorkerMessageHandledEvent::class => 'onMessageHandled',
            WorkerMessageFailedEvent::class => 'onMessageFailed',
        ];
    }

    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof ScheduledTask) {
            return;
        }

        // Keep start call even if is called in other subscribers, so that removing either
        // subscriber or reordering does not break the other's duration metric.
        $this->timingHelper->start($message);
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->emit($event->getEnvelope()->getMessage(), self::RESULT_SUCCESS);
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $this->emit($event->getEnvelope()->getMessage(), self::RESULT_FAILED);
    }

    private function emit(object $message, string $result): void
    {
        if (!$message instanceof ScheduledTask) {
            return;
        }

        $durationMs = $this->timingHelper->elapsedMs($message);
        if ($durationMs === null) {
            return;
        }

        $this->meter->emit(new ConfiguredMetric(
            name: 'scheduled_task.run.duration',
            value: $durationMs,
            labels: [
                'task_name' => $this->taskNameResolver->resolve($message::getTaskName()),
                'result' => $result,
            ],
        ));
    }
}
