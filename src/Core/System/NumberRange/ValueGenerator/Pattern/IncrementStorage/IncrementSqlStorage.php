<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\NumberRange\NumberRangeEntity;

class IncrementSqlStorage implements IncrementStorageInterface
{
    protected $connectorId = 'standard_pattern_connector';

    /**
     * @var Connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function pullState(NumberRangeEntity $configuration, $incrementBy = 1): string
    {
        $this->connection->beginTransaction();
        $stmt = $this->connection->executeQuery(
            'SELECT `last_value` FROM `number_range_state` WHERE number_range_id = :id FOR UPDATE',
            [
                'id' => Uuid::fromHexToBytes($configuration->getId()),
            ]
        );
        $lastNumber = $stmt->fetchColumn();

        if ($lastNumber === false) {
            $nextNumber = $configuration->getStart();
        } else {
            $nextNumber = $lastNumber + $incrementBy;
        }

        $this->connection->executeQuery(
            'INSERT `number_range_state` (`last_value`, `number_range_id`) VALUES (:value, :id) 
            ON DUPLICATE KEY UPDATE
            `last_value` = :value',
            [
                'value' => $nextNumber,
                'id' => Uuid::fromHexToBytes($configuration->getId()),
            ]
        );
        $this->connection->commit();

        return (string) $nextNumber;
    }

    public function getNext(NumberRangeEntity $configuration, $incrementBy = 1): string
    {
        $stmt = $this->connection->executeQuery(
            'SELECT `last_value` FROM `number_range_state` WHERE number_range_id = :id FOR UPDATE',
            [
                'id' => Uuid::fromHexToBytes($configuration->getId()),
            ]
        );
        $lastNumber = $stmt->fetchColumn();

        if ($lastNumber === false) {
            $nextNumber = $configuration->getStart();
        } else {
            $nextNumber = $lastNumber + $incrementBy;
        }

        return (string) $nextNumber;
    }
}
