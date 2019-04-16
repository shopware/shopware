<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;
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

    public function pullState(NumberRangeEntity $configuration): string
    {
        $varname = Uuid::randomHex();
        $stateId = Uuid::randomBytes();
        $this->connection->executeQuery(
            'INSERT `number_range_state` (`id`, `last_value`, `number_range_id`) VALUES (:stateId, :value, :id) 
                ON DUPLICATE KEY UPDATE
                `last_value` = @nr' . $varname . ' := `last_value`+1',
            [
                'value' => $configuration->getStart(),
                'id' => Uuid::fromHexToBytes($configuration->getId()),
                'stateId' => $stateId,
            ]
        );

        $stmt = $this->connection->executeQuery(
            'SELECT @nr' . $varname . ''
        );

        $lastNumber = $stmt->fetchColumn();

        if ($lastNumber === null || $lastNumber === false) {
            $nextNumber = $configuration->getStart();
        } else {
            $nextNumber = $lastNumber;
        }

        return (string) $nextNumber;
    }

    public function getNext(NumberRangeEntity $configuration): string
    {
        $stmt = $this->connection->executeQuery(
            'SELECT `last_value` FROM `number_range_state` WHERE number_range_id = :id',
            [
                'id' => Uuid::fromHexToBytes($configuration->getId()),
            ]
        );
        $lastNumber = $stmt->fetchColumn();

        if ($lastNumber === false) {
            $nextNumber = $configuration->getStart();
        } else {
            $nextNumber = $lastNumber + 1;
        }

        return (string) $nextNumber;
    }
}
