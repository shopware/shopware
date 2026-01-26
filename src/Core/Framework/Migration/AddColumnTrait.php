<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;

trait AddColumnTrait
{
    use ColumnExistsTrait;

    /**
     * @return bool true if the column was created, false if it already exists
     */
    protected function addColumn(
        Connection $connection,
        string $table,
        string $column,
        string $type,
        bool $nullable = true,
        string $default = 'NULL'
    ): bool {
        if ($this->columnExists($connection, $table, $column)) {
            return false;
        }

        // don't allow AFTER statements, it causes temporary tables which are extrem slow, because mysql has to copy whole tables
        $connection->executeStatement(
            'ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $type . ' ' . ($nullable ? 'NULL' : 'NOT NULL') . ' DEFAULT ' . $default . ';'
        );

        return true;
    }

    /**
     * Add a column using ALGORITHM=INSTANT for fast execution on large tables.
     *
     * This method enforces ALGORITHM=INSTANT which will fail fast if the operation
     * cannot be performed instantly (e.g., if the table has a hidden FTS_DOC_ID column
     * or if the database version doesn't support INSTANT for this operation).
     *
     * Use this method instead of addColumn() when you need to ensure the operation
     * completes quickly without table rebuilds. The operation will fail with an error
     * if INSTANT is not supported, preventing silent fallback to slower algorithms.
     *
     * Requirements:
     * - MySQL 8.0.12+ or MariaDB 10.3.2+ (Shopware minimum: MySQL 8.0.22, MariaDB 10.11)
     * - Column must be added at the end of the table (no AFTER clause)
     * - Table must not have a hidden FTS_DOC_ID column (from fulltext indexes)
     *
     * @param Connection $connection The database connection
     * @param string $table The table name
     * @param string $column The column name
     * @param string $type The column type (e.g., 'JSON', 'VARCHAR(255)')
     * @param bool $nullable Whether the column is nullable
     * @param string $default The default value (e.g., 'NULL', "'default'")
     *
     * @throws \Doctrine\DBAL\Exception If ALGORITHM=INSTANT is not supported
     *
     * @return bool true if the column was created, false if it already exists
     */
    protected function addColumnInstant(
        Connection $connection,
        string $table,
        string $column,
        string $type,
        bool $nullable = true,
        string $default = 'NULL'
    ): bool {
        if ($this->columnExists($connection, $table, $column)) {
            return false;
        }

        // ALGORITHM=INSTANT will fail fast if operation cannot be performed instantly
        // This prevents silent fallback to COPY algorithm which would rebuild the entire table
        $connection->executeStatement(
            'ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $type . ' ' . ($nullable ? 'NULL' : 'NOT NULL') . ' DEFAULT ' . $default . ', ALGORITHM=INSTANT;'
        );

        return true;
    }
}
