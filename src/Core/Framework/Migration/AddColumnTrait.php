<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;

trait AddColumnTrait
{
    use ColumnExistsTrait;

    /**
     * Add a column using ALGORITHM=INSTANT for fast, non-blocking execution.
     *
     * ALGORITHM=INSTANT ensures the column is added as a metadata-only change without
     * rebuilding the table. This is safe because this method only appends columns at the
     * end of the table (no AFTER/FIRST clause), which is fully INSTANT-compatible.
     *
     * If INSTANT is not supported for a specific case (e.g., the table has a hidden
     * FTS_DOC_ID column from fulltext indexes), the operation will fail fast instead
     * of silently falling back to a slow COPY algorithm.
     *
     * Requirements (already met by Shopware minimum versions):
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

        // ALGORITHM=INSTANT ensures fast metadata-only column addition.
        // No AFTER/FIRST clause is used, so the column is always appended – fully INSTANT-compatible.
        // If INSTANT is not possible, MySQL/MariaDB will raise an error instead of silently falling back to COPY.
        $connection->executeStatement(
            'ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $type . ' ' . ($nullable ? 'NULL' : 'NOT NULL') . ' DEFAULT ' . $default . ', ALGORITHM=INSTANT;'
        );

        return true;
    }
}
