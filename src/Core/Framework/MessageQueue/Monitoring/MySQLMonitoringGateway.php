<?php

namespace Shopware\Core\Framework\MessageQueue\Monitoring;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

class MySQLMonitoringGateway extends AbstractMonitoringGateway
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getDecorated(): AbstractMonitoringGateway
    {
        throw new DecorationPatternException(self::class);
    }

    public function increment(string $name): void
    {
        $payload = [
            'id' => Uuid::randomBytes(),
            'name' => $name,
            'count' => 1,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->connection->executeStatement('
            INSERT INTO `message_queue_stats` (`id`, `name`, `size`, `created_at`)
            VALUES (:id, :name, :count, :createdAt)
            ON DUPLICATE KEY UPDATE `size` = `size` + :count, `updated_at` = :createdAt
        ', $payload);
    }

    public function decrement(string $name): void
    {
        $this->connection->executeStatement(
            'UPDATE `message_queue_stats` SET `size` = `size` - :count WHERE `name` = :name AND `size` > 0;',
            ['name' => $name, 'count' => 1]
        );
    }

    public function get(): array
    {
        return $this->connection->fetchAll('SELECT `name`, `size` FROM message_queue_stats');
    }
}
