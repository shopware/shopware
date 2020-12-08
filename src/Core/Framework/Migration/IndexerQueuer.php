<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class IndexerQueuer
{
    public const INDEXER_KEY = 'core.scheduled_indexers';

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getIndexers(): array
    {
        $current = self::fetchCurrent($this->connection);

        if ($current !== null) {
            $decodedValue = json_decode($current['configuration_value'], true);

            return array_keys($decodedValue['_value'] ?? []);
        }

        return [];
    }

    public function finishIndexer(array $names): void
    {
        $current = self::fetchCurrent($this->connection);
        $indexerList = [];
        if ($current !== null) {
            $decodedValue = json_decode($current['configuration_value'], true);
            $indexerList = $decodedValue['_value'] ?? [];
        }

        $newList = [];
        foreach (array_keys($indexerList) as $indexer) {
            if (!\in_array($indexer, $names, true)) {
                $newList[$indexer] = 1;
            }
        }

        self::upsert($this->connection, $current['id'] ?? null, $newList);
    }

    public static function registerIndexer(Connection $connection, string $name, string $migration): void
    {
        $current = self::fetchCurrent($connection);

        $id = null;
        $indexerList = [];

        if ($current !== null) {
            $id = $current['id'];
            $decodedValue = json_decode($current['configuration_value'], true);
            $indexerList = $decodedValue['_value'] ?? [];
        }

        $indexerList[$name] = 1;

        self::upsert($connection, $id, $indexerList);
    }

    private static function upsert(Connection $connection, ?string $id, array $indexerList): void
    {
        $date = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $newValue = json_encode(['_value' => $indexerList]);

        if (empty($indexerList) && $id !== null) {
            $connection->delete('system_config', ['id' => $id]);

            return;
        }

        if ($id) {
            $connection->update(
                'system_config',
                ['configuration_value' => $newValue, 'updated_at' => $date],
                ['id' => $id]
            );
        } else {
            $connection->insert(
                'system_config',
                [
                    'id' => Uuid::randomBytes(),
                    'configuration_key' => self::INDEXER_KEY,
                    'configuration_value' => $newValue,
                    'created_at' => $date,
                ]
            );
        }
    }

    private static function fetchCurrent(Connection $connection): ?array
    {
        $currentRow = $connection->fetchAssoc(
            '
            SELECT *
            FROM system_config
            WHERE configuration_key = :key
            AND sales_channel_id IS NULL',
            ['key' => self::INDEXER_KEY]
        );

        if ($currentRow === false) {
            return null;
        }

        return $currentRow;
    }
}
