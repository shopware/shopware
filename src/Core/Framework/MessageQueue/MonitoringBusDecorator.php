<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

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

    private string $defaultTransportName;

    public function __construct(
        MessageBusInterface $inner,
        Connection $connection,
        string $defaultTransportName
    ) {
        $this->innerBus = $inner;
        $this->connection = $connection;
        $this->defaultTransportName = $defaultTransportName;
    }

    /**
     * Dispatches the given message to the inner Bus and Logs it.
     *
     * @param object|Envelope $message
     */
    public function dispatch($message, array $stamps = []): Envelope
    {
        $message = $this->innerBus->dispatch(Envelope::wrap($message, $stamps), $stamps);
        if ($this->wasSentToDefaultTransport($message)) {
            $this->incrementMessageQueueSize($message);
        }

        if ($this->wasReceivedByDefaultTransport($message)) {
            $this->decrementMessageQueueSize($message);
        }

        return $message;
    }

    private function incrementMessageQueueSize(Envelope $message): void
    {
        $this->connection->executeUpdate('
            INSERT INTO `message_queue_stats` (`id`, `name`, `size`, `created_at`)
            VALUES (:id, :name, 1, :createdAt)
            ON DUPLICATE KEY UPDATE `size` = `size` +1, `updated_at` = :createdAt
        ', [
            'id' => Uuid::randomBytes(),
            'name' => \get_class($message->getMessage()),
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function decrementMessageQueueSize(Envelope $message): void
    {
        $this->connection->executeUpdate('
            UPDATE `message_queue_stats`
            SET `size` = `size` - 1
            WHERE `name` = :name;
        ', [
            'name' => \get_class($message->getMessage()),
        ]);
    }

    private function wasSentToDefaultTransport(Envelope $message): bool
    {
        foreach ($message->all(SentStamp::class) as $stamp) {
            if ($stamp instanceof SentStamp && $stamp->getSenderAlias() === $this->defaultTransportName) {
                return true;
            }
        }

        return false;
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
