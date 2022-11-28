<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class CountHandledMessagesListener implements EventSubscriberInterface
{
    private int $handledMessages = 0;

    public static function getSubscribedEvents(): array
    {
        return [
            // must have higher priority than SendFailedMessageToFailureTransportListener
            WorkerMessageReceivedEvent::class => 'handled',
        ];
    }

    public function handled(WorkerMessageReceivedEvent $event): void
    {
        ++$this->handledMessages;
    }

    public function getHandledMessages(): int
    {
        return $this->handledMessages;
    }
}
