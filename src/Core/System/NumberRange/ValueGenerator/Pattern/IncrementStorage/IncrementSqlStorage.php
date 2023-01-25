<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('checkout')]
class IncrementSqlStorage extends AbstractIncrementStorage
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function reserve(array $config): int
    {
        $start = $config['start'] ?? 1;
        $varname = Uuid::randomHex();
        $stateId = Uuid::randomBytes();
        $this->connection->executeStatement(
            'INSERT `number_range_state` (`id`, `last_value`, `number_range_id`, `created_at`) VALUES (:stateId, :value, :id, :createdAt)
                ON DUPLICATE KEY UPDATE
                `last_value` = @nr' . $varname . ' := IF(`last_value`+1 > :value, `last_value`+1, :value)',
            [
                'value' => $start,
                'id' => Uuid::fromHexToBytes($config['id']),
                'stateId' => $stateId,
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $result = $this->connection->executeQuery('SELECT @nr' . $varname);

        $lastNumber = $result->fetchOne();

        if (!$lastNumber) {
            return $start;
        }

        return (int) $lastNumber;
    }

    public function preview(array $config): int
    {
        $result = $this->connection->executeQuery(
            'SELECT `last_value` FROM `number_range_state` WHERE number_range_id = :id',
            [
                'id' => Uuid::fromHexToBytes($config['id']),
            ]
        );
        $lastNumber = $result->fetchOne();

        $start = $config['start'] ?? 1;

        if (!$lastNumber || (int) $lastNumber < $start) {
            $nextNumber = $start;
        } else {
            $nextNumber = $lastNumber + 1;
        }

        return $nextNumber;
    }

    public function list(): array
    {
        /** @var array<string, string> $states */
        $states = $this->connection->fetchAllKeyValue('
            SELECT LOWER(HEX(`number_range_id`)), `last_value`
            FROM `number_range_state`
        ');

        return array_map(fn ($state) => (int) $state, $states);
    }

    public function set(string $configurationId, int $value): void
    {
        $stateId = Uuid::randomBytes();
        $this->connection->executeStatement(
            'INSERT `number_range_state` (`id`, `last_value`, `number_range_id`, `created_at`) VALUES (:stateId, :value, :id, :createdAt)
                ON DUPLICATE KEY UPDATE
                `last_value` = :value',
            [
                'value' => $value,
                'id' => Uuid::fromHexToBytes($configurationId),
                'stateId' => $stateId,
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    public function getDecorated(): AbstractIncrementStorage
    {
        throw new DecorationPatternException(self::class);
    }
}
