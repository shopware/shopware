<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.5.0 - Will be removed
 */
class IndexTableOperator
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @internal
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getIndexName(string $table, int $timestamp): string
    {
        Feature::triggerDeprecationOrThrow('v6.5.0.0', Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', null));

        return sprintf('%s_%s', $table, $timestamp);
    }

    public function createTable(string $table, string $indexName): void
    {
        Feature::triggerDeprecationOrThrow('v6.5.0.0', Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', null));

        $sql = str_replace(
            ['#indexName#', '#table#'],
            [EntityDefinitionQueryHelper::escape($indexName), EntityDefinitionQueryHelper::escape($table)],
            'DROP TABLE IF EXISTS #indexName#;
            CREATE TABLE #indexName# SELECT * FROM #table# LIMIT 0'
        );

        $this->connection->executeStatement($sql);
    }
}
