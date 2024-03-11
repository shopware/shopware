<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;

trait AddColumnTrait
{
    /**
     * @return bool true if the column was created, false if it already exists or was not created
     */
    protected function addColumn(Connection $connection, string $table, string $column, string $type, ?bool $nullable = true, string $default = 'NULL'): bool
    {
        if ($this->columnExists($connection, $table, $column)) {
            return false;
        }

        // don't allow AFTER statements, it causes temporary tables which are extrem slow, because mysql has to copy whole tables
        $connection->executeStatement(
            'ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $type . ' ' . ($nullable ? 'NULL' : 'NOT NULL') . ' DEFAULT ' . $default . ';'
        );

        return true;
    }

    protected function columnExists(Connection $connection, string $table, string $column): bool
    {
        $exists = $connection->fetchOne(
            'SHOW COLUMNS FROM `' . $table . '` WHERE `Field` LIKE :column',
            ['column' => $column]
        );

        return !empty($exists);
    }
}
