<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * @internal
 */
#[Package('system-settings')]
class EarlyReturnMessagesListener implements EventSubscriberInterface
{
    private bool $handled = false;

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
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
