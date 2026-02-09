<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;

trait AddColumnTrait
{
    use ColumnExistsTrait;

    /**
     * Add a column, preferring ALGORITHM=INSTANT for fast, non-blocking execution.
     *
     * Tries ALGORITHM=INSTANT first (metadata-only change, no table rebuild). If INSTANT
     * is not supported for the specific operation (e.g., expression defaults like JSON_OBJECT(),
     * tables with hidden FTS_DOC_ID columns from fulltext indexes), falls back to a regular
     * ALTER TABLE without algorithm hint.
     *
     * No AFTER/FIRST clause is used, so the column is always appended at the end of the table.
     *
     * Requirements for INSTANT (already met by Shopware minimum versions):
     * - MySQL 8.0.12+ or MariaDB 10.3.2+ (Shopware minimum: MySQL 8.0.22, MariaDB 10.11)
     *
     * @deprecated tag:v6.8.0 - reason:exception-change - Will throw {@see \Shopware\Core\Framework\Util\UtilException} instead of {@see \Doctrine\DBAL\Exception\TableNotFoundException}
     *
     * @param non-empty-string $table
     *
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

        $sql = 'ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $type . ' ' . ($nullable ? 'NULL' : 'NOT NULL') . ' DEFAULT ' . $default;

        try {
            // Try INSTANT first – fast metadata-only operation, no table rebuild.
            $connection->executeStatement($sql . ', ALGORITHM=INSTANT;');
        } catch (\Doctrine\DBAL\Exception $e) {
            // INSTANT not supported for this operation (e.g., expression defaults, fulltext tables).
            // Fall back to regular ALTER TABLE and let MySQL pick the best algorithm.
            $connection->executeStatement($sql . ';');
        }

        return true;
    }
}
