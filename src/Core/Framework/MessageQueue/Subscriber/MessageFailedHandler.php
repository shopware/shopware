<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class MessageFailedHandler implements EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
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

        $this->connection->executeUpdate('
            UPDATE `message_queue_stats`
            SET `size` = `size` - 1
            WHERE `name` = :name;
        ', [
            'name' => $this->getMessageName($event->getEnvelope()),
        ]);
    }

    /**
     * @param object|Envelope $message
     */
    private function getMessageName($message): string
    {
        return $message instanceof Envelope ? \get_class($message->getMessage()) : \get_class($message);
    }
}
