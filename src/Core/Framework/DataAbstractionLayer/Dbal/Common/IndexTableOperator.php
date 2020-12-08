<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;

class IndexTableOperator
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getIndexName(string $table, int $timestamp): string
    {
        return sprintf('%s_%s', $table, $timestamp);
    }

    public function createTable(string $table, string $indexName): void
    {
        $sql = str_replace(
            ['#indexName#', '#table#'],
            [EntityDefinitionQueryHelper::escape($indexName), EntityDefinitionQueryHelper::escape($table)],
            'DROP TABLE IF EXISTS #indexName#;
            CREATE TABLE #indexName# SELECT * FROM #table# LIMIT 0'
        );

        $this->connection->executeUpdate($sql);
    }
}
