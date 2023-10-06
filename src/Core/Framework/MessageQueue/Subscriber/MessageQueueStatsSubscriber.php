<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * @internal
 */
#[Package('system-settings')]
class MessageQueueStatsSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly IncrementGatewayRegistry $gatewayRegistry)
    {
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
        $name = $envelope->getMessage()::class;

        $gateway = $this->gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);

        if ($increment) {
            $gateway->increment('message_queue_stats', $name);

            return;
        }

        $gateway->decrement('message_queue_stats', $name);
    }
}
