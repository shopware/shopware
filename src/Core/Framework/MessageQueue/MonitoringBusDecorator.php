<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class MonitoringBusDecorator implements MessageBusInterface
{
    /**
     * @var MessageBusInterface
     */
    private $innerBus;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        MessageBusInterface $inner,
        Connection $connection
    ) {
        $this->innerBus = $inner;
        $this->connection = $connection;
    }

    /**
     * Dispatches the given message to the inner Bus and Logs it.
     *
     * @param object|Envelope $message
     */
    public function dispatch($message, array $stamps = []): Envelope
    {
        $messageName = $this->getMessageName($message);

        if ($this->isIncoming($message)) {
            $this->decrementMessageQueueSize($messageName);
        } else {
            $this->incrementMessageQueueSize($messageName);
        }

        return $this->innerBus->dispatch($message);
    }

    /**
     * @param object|Envelope $message
     */
    private function isIncoming($message): bool
    {
        return $message instanceof Envelope && $message->all(ReceivedStamp::class);
    }

    /**
     * @param object|Envelope $message
     */
    private function getMessageName($message): string
    {
        return $message instanceof Envelope ? get_class($message->getMessage()) : get_class($message);
    }

    private function incrementMessageQueueSize(string $name): void
    {
        $this->connection->executeQuery('
            INSERT INTO `message_queue_stats` (`id`, `name`, `size`, `created_at`)
            VALUES (:id, :name, 1, :createdAt)
            ON DUPLICATE KEY UPDATE `size` = `size` +1, `updated_at` = :createdAt
        ', [
            'id' => Uuid::randomBytes(),
            'name' => $name,
            'createdAt' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function decrementMessageQueueSize(string $name): void
    {
        $this->connection->executeUpdate('
            UPDATE `message_queue_stats`
            SET `size` = `size` - 1
            WHERE `name` = :name;
        ', [
            'name' => $name,
        ]);
    }
}
