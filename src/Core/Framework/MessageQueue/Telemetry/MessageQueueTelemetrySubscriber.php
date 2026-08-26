<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Service\MessageSizeCalculator;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * Emits the message-queue worker metrics off the Symfony worker events:
 *  - `messenger.message.size` — envelope size on receive,
 *  - `messenger.message.handled.count` — one per handle grouped by outcome (handled/retried/failed) and message group,
 *  - `messenger.message.handling.duration` — received→terminal wall time, per outcome and message group.
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
class MessageQueueTelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Meter $meter,
        private readonly MessageSizeCalculator $messageSizeCalculator,
        private readonly MessageGroupResolver $messageGroupResolver,
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
        // Keep start call even if is called in other subscribers, so that removing either
        // subscriber or reordering does not break the other's duration metric.
        $this->timingHelper->start($event->getEnvelope()->getMessage());

        $this->meter->emit(new ConfiguredMetric(
            name: 'messenger.message.size',
            value: $this->messageSizeCalculator->size($event->getEnvelope()),
        ));
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->emit($event->getEnvelope()->getMessage(), 'handled');
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        // separating final attempt
        $this->emit($event->getEnvelope()->getMessage(), $event->willRetry() ? 'retried' : 'failed');
    }

    private function emit(object $message, string $result): void
    {
        $messageGroup = $this->messageGroupResolver->resolve($message::class);

        $this->meter->emit(new ConfiguredMetric(
            name: 'messenger.message.handled.count',
            value: 1,
            labels: ['message_group' => $messageGroup, 'result' => $result],
        ));

        $durationMs = $this->timingHelper->elapsedMs($message);
        if ($durationMs === null) {
            return;
        }

        // Duration is recorded for every outcome, not just success: a slow failure (e.g. an external call timing out)
        // blocks a worker and cuts throughput just like a slow success.
        // The `result` label keeps the success and failure distributions separable, so a rise in failure latency
        // shows up on its own instead of being averaged into the healthy path.
        $this->meter->emit(new ConfiguredMetric(
            name: 'messenger.message.handling.duration',
            value: $durationMs,
            labels: ['message_group' => $messageGroup, 'result' => $result],
        ));
    }
}
