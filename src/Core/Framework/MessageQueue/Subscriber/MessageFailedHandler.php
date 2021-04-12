<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class MessageFailedHandler implements EventSubscriberInterface
{
    private Connection $connection;

    private string $defaultTransportName;

    public function __construct(Connection $connection, string $defaultTransportName)
    {
        $this->connection = $connection;
        $this->defaultTransportName = $defaultTransportName;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // must have higher priority than SendFailedMessageToFailureTransportListener
            WorkerMessageFailedEvent::class => ['onMessageFailed', 99],
        ];
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope();

        if (!$this->wasReceivedByDefaultTransport($message)) {
            return;
        }

        $name = \get_class($message->getMessage());
        $this->connection->executeUpdate('
            UPDATE `message_queue_stats`
            SET `size` = `size` - 1
            WHERE `name` = :name;
        ', ['name' => $name]);
    }

    private function wasReceivedByDefaultTransport(Envelope $message): bool
    {
        foreach ($message->all(ReceivedStamp::class) as $stamp) {
            if ($stamp instanceof ReceivedStamp && $stamp->getTransportName() === $this->defaultTransportName) {
                return true;
            }
        }

        return false;
    }
}
