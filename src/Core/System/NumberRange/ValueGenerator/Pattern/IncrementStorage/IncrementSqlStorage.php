<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\NumberRangeEntity;

/**
 * @deprecated tag:v6.5.0 won't implement IncrementStorageInterface anymore, use AbstractIncrementStorage instead
 */
class IncrementSqlStorage extends AbstractIncrementStorage implements IncrementStorageInterface
{
    /**
     * @deprecated tag:v6.5.0 property will be removed as it is unused
     */
    protected string $connectorId = 'standard_pattern_connector';

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @deprecated tag:v6.5.0 will be removed use `reserve()` instead
     */
    public function pullState(NumberRangeEntity $configuration): string
    {
        Feature::triggerDeprecationOrThrow(
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'IncrementSqlStorage::reserve()'),
            'v6.5.0.0'
        );

        $config = [
            'id' => $configuration->getId(),
            'start' => $configuration->getStart(),
            'pattern' => $configuration->getPattern() ?? '',
        ];

        return (string) $this->reserve($config);
    }

    /**
     * @deprecated tag:v6.5.0 will be removed use `preview()` instead
     */
    public function getNext(NumberRangeEntity $configuration): string
    {
        Feature::triggerDeprecationOrThrow(
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'IncrementSqlStorage::preview()'),
            'v6.5.0.0'
        );

        $config = [
            'id' => $configuration->getId(),
            'start' => $configuration->getStart(),
            'pattern' => $configuration->getPattern() ?? '',
        ];

        return (string) $this->preview($config);
    }

    public function reserve(array $config): int
    {
        $start = $config['start'] ?? 1;
        $varname = Uuid::randomHex();
        $stateId = Uuid::randomBytes();
        $this->connection->executeUpdate(
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

        $stmt = $this->connection->executeQuery('SELECT @nr' . $varname);

        $lastNumber = $stmt->fetchColumn();

        if (!$lastNumber) {
            return $start;
        }

        return (int) $lastNumber;
    }

    public function preview(array $config): int
    {
        $stmt = $this->connection->executeQuery(
            'SELECT `last_value` FROM `number_range_state` WHERE number_range_id = :id',
            [
                'id' => Uuid::fromHexToBytes($config['id']),
            ]
        );
        $lastNumber = $stmt->fetchColumn();

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
        $this->connection->executeUpdate(
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
