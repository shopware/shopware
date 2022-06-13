<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class MessageQueueStatsSubscriber implements EventSubscriberInterface
{
    private IncrementGatewayRegistry $gatewayRegistry;

    /**
     * @internal
     */
    public function __construct(IncrementGatewayRegistry $gatewayRegistry)
    {
        $this->gatewayRegistry = $gatewayRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // must have higher priority than SendFailedMessageToFailureTransportListener
            WorkerMessageFailedEvent::class => ['onMessageFailed', 99],
            WorkerMessageHandledEvent::class => 'onMessageHandled',
            SendMessageToTransportsEvent::class => ['onMessageSent', 99],
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

    private function handle(Envelope $envelope, bool $increment): void
    {
        $name = \get_class($envelope->getMessage());

        $gateway = $this->gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);

        if ($increment) {
            $gateway->increment('message_queue_stats', $name);

            return;
        }

        $gateway->decrement('message_queue_stats', $name);
    }
}
