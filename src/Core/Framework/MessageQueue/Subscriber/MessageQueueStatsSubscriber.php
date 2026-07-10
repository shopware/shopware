<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\StatsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * @internal
 */
#[Package('framework')]
class MessageQueueStatsSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * @deprecated tag:v6.8.0 - Property will be removed. The increment-based message queue statistics are deprecated.
         */
        private readonly IncrementGatewayRegistry $gatewayRegistry,
        private readonly StatsService $statsService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        $events = [
            WorkerMessageHandledEvent::class => 'onMessageHandled',
        ];

        if (!Feature::isActive('v6.8.0.0')) {
            $events[WorkerMessageFailedEvent::class] = ['onMessageFailed', 99];
            $events[SendMessageToTransportsEvent::class] = ['onMessageSent', 99];
        }

        return $events;
    }

    /**
     * @deprecated tag:v6.8.0 - Method will be removed. The increment-based message queue statistics are deprecated.
     *
     * @phpstan-ignore shopware.deprecatedMethod (not triggering deprecation to avoid polluting logs)
     */
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
        $this->statsService->registerMessage($event->getEnvelope());
    }

    /**
     * @deprecated tag:v6.8.0 - Method will be removed. The increment-based message queue statistics are deprecated.
     *
     * @phpstan-ignore shopware.deprecatedMethod (not triggering deprecation to avoid polluting logs)
     */
    public function onMessageSent(SendMessageToTransportsEvent $event): void
    {
        $this->handle($event->getEnvelope(), true);
    }

    /**
     * @deprecated tag:v6.8.0 - Method will be removed. The increment-based message queue statistics are deprecated.
     */
    private function handle(Envelope $envelope, bool $increment): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            return;
        }

        $name = $envelope->getMessage()::class;

        $gateway = $this->gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);

        if ($increment) {
            $gateway->increment('message_queue_stats', $name);

            return;
        }

        $gateway->decrement('message_queue_stats', $name);
    }
}
