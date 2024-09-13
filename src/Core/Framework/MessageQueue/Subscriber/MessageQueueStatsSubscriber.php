<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Histogram;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;

/**
 * @internal
 */
#[Package('services-settings')]
class MessageQueueStatsSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IncrementGatewayRegistry $gatewayRegistry,
        private readonly Meter $meter
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // must have higher priority than SendFailedMessageToFailureTransportListener
            WorkerMessageFailedEvent::class => ['onMessageFailed', 99],
            WorkerMessageHandledEvent::class => 'onMessageHandled',
            SendMessageToTransportsEvent::class => ['onMessageSent', 99],
            WorkerMessageReceivedEvent::class => 'onMessageReceived',
        ];
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $this->handle($event->getEnvelope(), false);
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->handle($event->getEnvelope(), false);
    }

    public function onMessageSent(SendMessageToTransportsEvent $event): void
    {
        $this->handle($event->getEnvelope(), true);
    }

    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->emitMessageSizeMetric($event);
    }

    private function handle(Envelope $envelope, bool $increment): void
    {
        $name = $envelope->getMessage()::class;

        $gateway = $this->gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);

        if ($increment) {
            $gateway->increment('message_queue_stats', $name);

            return;
        }

        $gateway->decrement('message_queue_stats', $name);
    }

    private function emitMessageSizeMetric(WorkerMessageReceivedEvent $event): void
    {
        $stamp = $event->getEnvelope()->last(SerializedMessageStamp::class);
        if ($stamp === null) {
            return;
        }

        $this->meter->emit(new Histogram(
            name: 'messenger.message.size',
            value: \strlen($stamp->getSerializedMessage()),
            description: 'Size of the message in bytes',
            unit: 'byte',
        ));
    }
}
