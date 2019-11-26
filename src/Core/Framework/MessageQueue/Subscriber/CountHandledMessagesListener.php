<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;

class CountHandledMessagesListener extends StopWorkerOnTimeLimitListener
{
    private $handledMessages = 0;

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (!$event->isWorkerIdle()) {
            ++$this->handledMessages;
        }

        parent::onWorkerRunning($event);
    }

    public function getHandledMessages(): int
    {
        return $this->handledMessages;
    }
}
