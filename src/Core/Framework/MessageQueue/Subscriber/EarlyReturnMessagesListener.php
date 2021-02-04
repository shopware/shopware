<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

class EarlyReturnMessagesListener implements EventSubscriberInterface
{
    /**
     * @var bool
     */
    private $handled = false;

    public static function getSubscribedEvents()
    {
        return [
            WorkerRunningEvent::class => 'earlyReturn',
            WorkerMessageHandledEvent::class => 'handled',
        ];
    }

    public function handled(): void
    {
        $this->handled = true;
    }

    public function earlyReturn(WorkerRunningEvent $event): void
    {
        if ($this->handled) {
            return;
        }

        $event->getWorker()->stop();
        $this->handled = false;
    }
}
