<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Common;

use Doctrine\DBAL\Connection;

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

    public function renameTable(string $table, \DateTime $timestamp): void
    {
        $this->connection->transactional(function () use ($timestamp, $table) {
            $name = $this->getIndexName($table, $timestamp);
            $this->connection->executeUpdate('DROP TABLE ' . $table);
            $this->connection->executeUpdate('ALTER TABLE ' . $name . ' RENAME TO ' . $table);
        });
    }

    public function createTable(string $table, \DateTime $timestamp): void
    {
        $name = $this->getIndexName($table, $timestamp);

        $this->connection->executeUpdate('
            DROP TABLE IF EXISTS ' . $name . ';
            CREATE TABLE ' . $name . ' SELECT * FROM ' . $table . ' LIMIT 0
        ');
    }
}
