<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Repairs a fixed, known list of legacy foreign keys so every shop ends up with the canonical
 * definition a fresh installation creates.
 *
 * Migration1574082635AddOrderLineItemProductId and Migration1620215586FixManufacturerForeignKey
 * originally created their foreign keys without a constraint name, so shops that executed them
 * before the migrations were corrected (PR #14431) carry auto-generated names such as
 * `product_ibfk_1`. Even older upgrade paths can carry the constraint without the `version_id`
 * columns. Both drifts break on MySQL 8.4: foreign key names are unique per database there, so
 * auto-generated names collide on dump imports, and `restrict_fk_on_non_standard_key=ON`
 * combined with MySQL bug #118151 makes unrelated `ALTER TABLE` statements on the referenced
 * table fail with error 1553 while an incomplete-key reference exists.
 *
 * Each listed constraint is deterministically reconciled: whatever variant currently exists
 * (auto-generated name, missing version column, missing entirely) is replaced by the canonical
 * definition. Nothing outside this list is touched.
 *
 * @see https://bugs.mysql.com/bug.php?id=118151
 *
 * @internal
 */
#[Package('framework')]
class Migration1784812800RepairLegacyForeignKeys extends MigrationStep
{
    /**
     * Canonical definitions as created by the corrected migrations on fresh installations.
     */
    private const REPAIRS = [
        [
            'table' => 'product',
            'constraint' => 'fk.product.product_manufacturer',
            'columns' => ['product_manufacturer_id', 'product_manufacturer_version_id'],
            'referencedTable' => 'product_manufacturer',
            'referencedColumns' => ['id', 'version_id'],
            'deleteRule' => 'SET NULL',
            'updateRule' => 'CASCADE',
        ],
        [
            'table' => 'order_line_item',
            'constraint' => 'fk.order_line_item.product',
            'columns' => ['product_id', 'product_version_id'],
            'referencedTable' => 'product',
            'referencedColumns' => ['id', 'version_id'],
            'deleteRule' => 'SET NULL',
            'updateRule' => 'CASCADE',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1784812800;
    }

    public function update(Connection $connection): void
    {
        foreach (self::REPAIRS as $repair) {
            $existing = $this->fetchForeignKeysOnColumn(
                $connection,
                $repair['table'],
                $repair['referencedTable'],
                $repair['columns'][0]
            );

            if ($this->isCanonical($existing, $repair)) {
                continue;
            }

            if (!$this->allColumnsExist($connection, $repair['table'], $repair['columns'])) {
                continue;
            }

            $this->replaceForeignKey($connection, $existing, $repair);
        }
    }

    /**
     * @param array<string, array{columnPairs: list<list<string>>, deleteRule: string, updateRule: string}> $existing
     * @param array{table: string, constraint: string, columns: list<string>, referencedTable: string, referencedColumns: list<string>, deleteRule: string, updateRule: string} $repair
     */
    private function isCanonical(array $existing, array $repair): bool
    {
        if (\count($existing) !== 1 || !isset($existing[$repair['constraint']])) {
            return false;
        }

        $foreignKey = $existing[$repair['constraint']];

        return $foreignKey['columnPairs'] === [
            [$repair['columns'][0], $repair['referencedColumns'][0]],
            [$repair['columns'][1], $repair['referencedColumns'][1]],
        ]
            && $foreignKey['deleteRule'] === $repair['deleteRule']
            && $foreignKey['updateRule'] === $repair['updateRule'];
    }

    /**
     * @param array<string, array{columnPairs: list<list<string>>, deleteRule: string, updateRule: string}> $existing
     * @param array{table: string, constraint: string, columns: list<string>, referencedTable: string, referencedColumns: list<string>, deleteRule: string, updateRule: string} $repair
     */
    private function replaceForeignKey(Connection $connection, array $existing, array $repair): void
    {
        // Data was already accepted under the old constraint; re-validating every child row can
        // take minutes to hours on `product` / `order_line_item` and would abort the update on
        // legacy orphans (the reason PR #14431 avoided a migration back then).
        $previousFkChecks = (int) $connection->fetchOne('SELECT @@SESSION.foreign_key_checks');
        $connection->executeStatement('SET SESSION foreign_key_checks = 0');
        // MySQL bug #118151: while any non-standard foreign key against a table exists, ALTER
        // statements on that table fail. The repair itself is such an ALTER, so relax the guard
        // for this session; the recreated constraints are standard and unaffected by it.
        $previousGuard = $this->relaxNonStandardFkGuard($connection);

        try {
            foreach (\array_keys($existing) as $constraintName) {
                $this->dropForeignKeyIfExists($connection, $repair['table'], $constraintName);
            }

            $connection->executeStatement(\sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`, `%s`) REFERENCES `%s` (`%s`, `%s`) ON DELETE %s ON UPDATE %s',
                $repair['table'],
                $repair['constraint'],
                $repair['columns'][0],
                $repair['columns'][1],
                $repair['referencedTable'],
                $repair['referencedColumns'][0],
                $repair['referencedColumns'][1],
                $repair['deleteRule'],
                $repair['updateRule']
            ));
        } finally {
            $connection->executeStatement(\sprintf('SET SESSION foreign_key_checks = %d', $previousFkChecks));
            if ($previousGuard !== null) {
                $connection->executeStatement(\sprintf('SET SESSION restrict_fk_on_non_standard_key = %d', $previousGuard));
            }
        }
    }

    /**
     * Finds every foreign key on $table that references $referencedTable and starts with
     * $firstColumn, keyed by constraint name — the legacy variants differ in name and column
     * count, but always start with the same column.
     *
     * @return array<string, array{columnPairs: list<list<string>>, deleteRule: string, updateRule: string}>
     */
    private function fetchForeignKeysOnColumn(Connection $connection, string $table, string $referencedTable, string $firstColumn): array
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT
                kcu.CONSTRAINT_NAME AS constraintName,
                kcu.COLUMN_NAME AS columnName,
                kcu.REFERENCED_COLUMN_NAME AS referencedColumnName,
                rc.DELETE_RULE AS deleteRule,
                rc.UPDATE_RULE AS updateRule
            FROM information_schema.KEY_COLUMN_USAGE kcu
            INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                AND rc.TABLE_NAME = kcu.TABLE_NAME
            WHERE kcu.TABLE_SCHEMA = DATABASE()
                AND kcu.TABLE_NAME = :tableName
                AND kcu.REFERENCED_TABLE_NAME = :referencedTableName
            ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION',
            ['tableName' => $table, 'referencedTableName' => $referencedTable]
        );

        $foreignKeys = [];
        foreach ($rows as $row) {
            $constraintName = (string) $row['constraintName'];
            $foreignKeys[$constraintName]['columnPairs'][] = [(string) $row['columnName'], (string) $row['referencedColumnName']];
            $foreignKeys[$constraintName]['deleteRule'] = \strtoupper((string) $row['deleteRule']);
            $foreignKeys[$constraintName]['updateRule'] = \strtoupper((string) $row['updateRule']);
        }

        return \array_filter(
            $foreignKeys,
            static fn (array $foreignKey): bool => $foreignKey['columnPairs'][0][0] === $firstColumn
        );
    }

    /**
     * @param list<string> $columns
     */
    private function allColumnsExist(Connection $connection, string $table, array $columns): bool
    {
        $existing = $connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName AND COLUMN_NAME IN (:columnNames)',
            ['tableName' => $table, 'columnNames' => $columns],
            ['columnNames' => ArrayParameterType::STRING]
        );

        return \count($existing) === \count($columns);
    }

    /**
     * @return int|null previous value of the guard, or null if the variable is unsupported
     *                  (MariaDB / MySQL <8.4)
     */
    private function relaxNonStandardFkGuard(Connection $connection): ?int
    {
        try {
            $previous = (int) $connection->fetchOne('SELECT @@SESSION.restrict_fk_on_non_standard_key');
        } catch (\Throwable) {
            return null;
        }

        $connection->executeStatement('SET SESSION restrict_fk_on_non_standard_key = OFF');

        return $previous;
    }
}
