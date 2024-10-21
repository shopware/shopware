<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Service\MessageSizeCalculator;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * @internal
 */
#[Package('services-settings')]
class MessageQueueTelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Meter $meter,
        private readonly MessageSizeCalculator $messageSizeCalculator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'emitMessageSizeMetric',
        ];
    }

    public function emitMessageSizeMetric(WorkerMessageReceivedEvent $event): void
    {
        $this->meter->emit(new ConfiguredMetric(
            name: 'messenger.message.size',
            value: $this->messageSizeCalculator->size($event->getEnvelope()),
        ));
    }
}
