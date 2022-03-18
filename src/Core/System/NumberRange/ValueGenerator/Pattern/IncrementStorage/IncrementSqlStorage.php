<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
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
        $config = [
            'id' => $configuration->getId(),
            'start' => $configuration->getStart(),
            'pattern' => $configuration->getPattern() ?? '',
        ];

        return $this->reserve($config);
    }

    /**
     * @deprecated tag:v6.5.0 will be removed use `preview()` instead
     */
    public function getNext(NumberRangeEntity $configuration): string
    {
        $config = [
            'id' => $configuration->getId(),
            'start' => $configuration->getStart(),
            'pattern' => $configuration->getPattern() ?? '',
        ];

        return $this->preview($config);
    }

    public function reserve(array $config): string
    {
        $varname = Uuid::randomHex();
        $stateId = Uuid::randomBytes();
        $this->connection->executeUpdate(
            'INSERT `number_range_state` (`id`, `last_value`, `number_range_id`, `created_at`) VALUES (:stateId, :value, :id, :createdAt)
                ON DUPLICATE KEY UPDATE
                `last_value` = @nr' . $varname . ' := IF(`last_value`+1 > :value, `last_value`+1, :value)',
            [
                'value' => $config['start'],
                'id' => Uuid::fromHexToBytes($config['id']),
                'stateId' => $stateId,
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $stmt = $this->connection->executeQuery('SELECT @nr' . $varname);

        $lastNumber = $stmt->fetchColumn();

        if ($lastNumber === null || $lastNumber === false) {
            $nextNumber = $config['start'];
        } else {
            $nextNumber = $lastNumber;
        }

        return (string) $nextNumber;
    }

    public function preview(array $config): string
    {
        $stmt = $this->connection->executeQuery(
            'SELECT `last_value` FROM `number_range_state` WHERE number_range_id = :id',
            [
                'id' => Uuid::fromHexToBytes($config['id']),
            ]
        );
        $lastNumber = $stmt->fetchColumn();

        if ($lastNumber === false || (int) $lastNumber < (int) $config['start']) {
            $nextNumber = $config['start'];
        } else {
            $nextNumber = $lastNumber + 1;
        }

        return (string) $nextNumber;
    }

    public function getDecorated(): AbstractIncrementStorage
    {
        throw new DecorationPatternException(self::class);
    }
}
