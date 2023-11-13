<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * @internal
 */
#[Package('system-settings')]
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
