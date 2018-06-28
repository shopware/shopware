<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal\Common;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\ORM\Dbal\EntityDefinitionQueryHelper;

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

    public function getIndexName(string $table, ?\DateTime $timestamp): string
    {
        if ($timestamp === null) {
            return $table;
        }

        return $table . '_' . $timestamp->getTimestamp();
    }

    public function createTable(string $table, \DateTime $timestamp): void
    {
        $name = $this->getIndexName($table, $timestamp);

        $sql = str_replace(
            ['#name#', '#table#'],
            [EntityDefinitionQueryHelper::escape($name), EntityDefinitionQueryHelper::escape($table)],
            'DROP TABLE IF EXISTS #name#;
            CREATE TABLE #name# SELECT * FROM #table# LIMIT 0'
        );

        $this->connection->executeUpdate($sql);
    }
}
