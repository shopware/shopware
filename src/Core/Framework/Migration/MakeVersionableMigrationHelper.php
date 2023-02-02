<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;

class MakeVersionableMigrationHelper
{
    private const DROP_FOREIGN_KEY = 'ALTER TABLE `%s` DROP FOREIGN KEY `%s`';
    private const DROP_KEY = 'ALTER TABLE `%s` DROP KEY `%s`';
    private const ADD_FOREIGN_KEY = 'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (%s, `%s`) REFERENCES `%s` (%s, `%s`) ON DELETE %s ON UPDATE CASCADE';
    private const ADD_NEW_COLUMN_WITH_DEFAULT = 'ALTER TABLE `%s` ADD `%s` binary(16) NOT NULL DEFAULT 0x%s AFTER `%s`';
    private const ADD_NEW_COLUMN_NULLABLE = 'ALTER TABLE `%s` ADD `%s` binary(16) NULL AFTER `%s`';
    private const MODIFY_PRIMARY_KEY_IN_MAIN = 'ALTER TABLE `%s` DROP PRIMARY KEY, ADD `%s` binary(16) NOT NULL DEFAULT 0x%s AFTER `%s`, ADD PRIMARY KEY (`%s`, `%s`)';
    private const MODIFY_PRIMARY_KEY_IN_RELATION = 'ALTER TABLE `%s` DROP PRIMARY KEY, ADD PRIMARY KEY (%s, `%s`)';
    private const ADD_KEY = 'ALTER TABLE `%s` ADD INDEX `fk.%s.%s` (%s)';
    private const FIND_RELATIONSHIPS_QUERY = <<<EOD
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
	REFERENCED_TABLE_SCHEMA = '%s'
    AND REFERENCED_TABLE_NAME = '%s';
EOD;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var AbstractSchemaManager
     */
    private $schemaManager;

    public function __construct(
        Connection $connection
    ) {
        $this->connection = $connection;
        $this->schemaManager = $connection->getSchemaManager();
    }

    public function getRelationData(string $tableName, string $keyColumn): array
    {
        $data = $this->fetchRelationData($tableName);

        return $this->hydrateForeignKeyData($data, $keyColumn);
    }

    public function createSql(array $keyStructures, string $tableName, string $newColumnName, string $defaultValue): array
    {
        return array_filter(array_merge(
            $this->createDropKeysPlaybookEntries($keyStructures),
            [$this->createModifyPrimaryKeyQuery($tableName, $newColumnName, $defaultValue)],
            $this->createAddKeysPlaybookEntries($keyStructures, $newColumnName, $tableName),
            $this->createAddColumnsAndKeysPlaybookEntries($newColumnName, $keyStructures, $defaultValue)
        ));
    }

    private function createDropKeysPlaybookEntries(array $keyStructures): array
    {
        $playbook = [];
        foreach ($keyStructures as $constraintName => $keyStructure) {
            $indexes = $this->schemaManager->listTableIndexes($keyStructure['TABLE_NAME']);

            $playbook[] = sprintf(self::DROP_FOREIGN_KEY, $keyStructure['TABLE_NAME'], $constraintName);

            if (\array_key_exists(strtolower($constraintName), $indexes)) {
                $playbook[] = sprintf(self::DROP_KEY, $keyStructure['TABLE_NAME'], $constraintName);
            }
        }

        return $playbook;
    }

    private function createAddColumnsAndKeysPlaybookEntries(string $newColumnName, array $keyStructures, string $default): array
    {
        $playbook = [];
        $duplicateColumnNamePrevention = [];

        foreach ($keyStructures as $constraintName => $keyStructure) {
            $foreignKeyColumnName = $keyStructure['REFERENCED_TABLE_NAME'] . '_' . $newColumnName;

            if (isset($duplicateColumnNamePrevention[$keyStructure['TABLE_NAME']])) {
                $foreignKeyColumnName .= '_' . $duplicateColumnNamePrevention[$keyStructure['TABLE_NAME']];
            }

            $fk = $this->findForeignKeyDefinition($keyStructure);

            $playbook[] = $this->determineAddColumnSql($fk, $keyStructure, $foreignKeyColumnName, $default);
            $playbook[] = $this->determineModifyPrimaryKeySql($keyStructure, $foreignKeyColumnName);
            $playbook[] = $this->getAddForeignKeySql($keyStructure, $constraintName, $foreignKeyColumnName, $newColumnName, $fk);

            if (isset($duplicateColumnNamePrevention[$keyStructure['TABLE_NAME']])) {
                ++$duplicateColumnNamePrevention[$keyStructure['TABLE_NAME']];
            } else {
                $duplicateColumnNamePrevention[$keyStructure['TABLE_NAME']] = 1;
            }
        }

        return $playbook;
    }

    private function createAddKeysPlaybookEntries(array $keyStructures, string $newColumnName, string $tableName): array
    {
        $playbook = [];
        foreach ($keyStructures as $keyStructure) {
            if (\count($keyStructure['REFERENCED_COLUMN_NAME']) < 2) {
                continue;
            }

            $keyColumns = $keyStructure['REFERENCED_COLUMN_NAME'];
            $keyColumns[] = $newColumnName;
            $uniqueName = implode('_', $keyColumns);

            $playbook[$uniqueName] = sprintf(self::ADD_KEY, $tableName, $tableName, $uniqueName, $this->implodeColumns($keyColumns));
        }

        return array_values($playbook);
    }

    private function implodeColumns(array $columns): string
    {
        return implode(',', array_map(function (string $column): string {
            return '`' . $column . '`';
        }, $columns));
    }

    private function isEqualForeignKey(ForeignKeyConstraint $constraint, string $foreignTable, array $foreignFieldNames): bool
    {
        if ($constraint->getForeignTableName() !== $foreignTable) {
            return false;
        }

        return \count(array_diff($constraint->getForeignColumns(), $foreignFieldNames)) === 0;
    }

    private function hydrateForeignKeyData(array $data, string $keyColumnName): array
    {
        $hydratedData = $this->mapHydrateForeignKeyData($data);

        return $this->filterHydrateForeignKeyData($hydratedData, $keyColumnName);
    }

    private function mapHydrateForeignKeyData(array $data): array
    {
        $hydratedData = [];

        foreach ($data as $entry) {
            $constraintName = $entry['CONSTRAINT_NAME'];

            if (!isset($hydratedData[$constraintName])) {
                $hydratedData[$constraintName] = [
                    'TABLE_NAME' => $entry['TABLE_NAME'],
                    'COLUMN_NAME' => [$entry['COLUMN_NAME']],
                    'REFERENCED_TABLE_NAME' => $entry['REFERENCED_TABLE_NAME'],
                    'REFERENCED_COLUMN_NAME' => [$entry['REFERENCED_COLUMN_NAME']],
                ];

                continue;
            }

            $hydratedData[$constraintName]['COLUMN_NAME'][] = $entry['COLUMN_NAME'];
            $hydratedData[$constraintName]['REFERENCED_COLUMN_NAME'][] = $entry['REFERENCED_COLUMN_NAME'];
        }

        return $hydratedData;
    }

    private function filterHydrateForeignKeyData(array $hydratedData, string $keyColumnName): array
    {
        $hydratedData = array_filter($hydratedData, function (array $entry) use ($keyColumnName): bool {
            return \in_array($keyColumnName, $entry['REFERENCED_COLUMN_NAME'], true);
        });

        return $hydratedData;
    }

    private function fetchRelationData(string $tableName): array
    {
        $databaseName = $this->connection->fetchColumn('SELECT DATABASE()');
        $query = sprintf(self::FIND_RELATIONSHIPS_QUERY, $databaseName, $tableName);

        return $this->connection->fetchAll($query);
    }

    private function createModifyPrimaryKeyQuery(string $tableName, string $newColumnName, string $defaultValue): string
    {
        $pk = $this->schemaManager->listTableIndexes($tableName)['primary'];

        if (\count($pk->getColumns()) !== 1) {
            throw new \RuntimeException(
                'Tables with multi column primary keys not supported. Maybe this migration did already run.'
            );
        }
        $pkName = current($pk->getColumns());

        return sprintf(self::MODIFY_PRIMARY_KEY_IN_MAIN, $tableName, $newColumnName, $defaultValue, $pkName, $pkName, $newColumnName);
    }

    private function findForeignKeyDefinition(array $keyStructure): ForeignKeyConstraint
    {
        $fks = $this->schemaManager->listTableForeignKeys($keyStructure['TABLE_NAME']);
        $fk = null;

        foreach ($fks as $fk) {
            if ($this->isEqualForeignKey($fk, $keyStructure['REFERENCED_TABLE_NAME'], $keyStructure['REFERENCED_COLUMN_NAME'])) {
                break;
            }
        }

        if ($fk === null) {
            throw new \LogicException('Unable to find a foreign key that was previously selected');
        }

        return $fk;
    }

    private function determineAddColumnSql(ForeignKeyConstraint $fk, array $keyStructure, string $foreignKeyColumnName, string $default): string
    {
        $isNullable = $fk->getOption('onDelete') === 'SET NULL';
        if ($isNullable) {
            $addColumnSql = sprintf(
                self::ADD_NEW_COLUMN_NULLABLE,
                $keyStructure['TABLE_NAME'],
                $foreignKeyColumnName,
                end($keyStructure['COLUMN_NAME'])
            );
        } else {
            $addColumnSql = sprintf(
                self::ADD_NEW_COLUMN_WITH_DEFAULT,
                $keyStructure['TABLE_NAME'],
                $foreignKeyColumnName,
                $default,
                end($keyStructure['COLUMN_NAME'])
            );
        }

        return $addColumnSql;
    }

    private function getAddForeignKeySql(array $keyStructure, string $constraintName, string $foreignKeyColumnName, string $newColumnName, ForeignKeyConstraint $fk): string
    {
        return sprintf(
            self::ADD_FOREIGN_KEY,
            $keyStructure['TABLE_NAME'],
            $constraintName,
            $this->implodeColumns($keyStructure['COLUMN_NAME']),
            $foreignKeyColumnName,
            $keyStructure['REFERENCED_TABLE_NAME'],
            $this->implodeColumns($keyStructure['REFERENCED_COLUMN_NAME']),
            $newColumnName,
            $fk->getOption('onDelete') ?? 'RESTRICT'
        );
    }

    private function determineModifyPrimaryKeySql(array $keyStructure, string $foreignKeyColumnName): ?string
    {
        $indexes = $this->schemaManager->listTableIndexes($keyStructure['TABLE_NAME']);
        if (isset($indexes['primary']) && \count(array_intersect($indexes['primary']->getColumns(), $keyStructure['COLUMN_NAME']))) {
            return sprintf(
                self::MODIFY_PRIMARY_KEY_IN_RELATION,
                $keyStructure['TABLE_NAME'],
                $this->implodeColumns($indexes['primary']->getColumns()),
                $foreignKeyColumnName
            );
        }

        return null;
    }
}
