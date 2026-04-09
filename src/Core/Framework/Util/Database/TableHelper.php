<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column as DbalColumn;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Doctrine\DBAL\Schema\Index as DbalIndex;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\UtilException;

/**
 * Covered by {@see \Shopware\Tests\Integration\Core\Framework\Util\Database\TableHelperTest}
 *
 * @final
 *
 * @internal
 *
 * @template TPlatform of AbstractPlatform
 */
#[Package('framework')]
class TableHelper
{
    private function __construct()
    {
    }

    /**
     * @throws TableHelperException
     */
    public static function tableExists(Connection $connection, string $tableName): bool
    {
        try {
            return self::getSchemaManager($connection)->tableExists($tableName);
        } catch (TableHelperException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw UtilException::databaseTableHelperException(__FUNCTION__, $e);
        }
    }

    /**
     * @param non-empty-string $tableName
     *
     * @throws TableHelperException
     */
    public static function getTable(Connection $connection, string $tableName): Table
    {
        try {
            $dbalTable = self::getSchemaManager($connection)->introspectTableByUnquotedName($tableName);

            return new Table(
                columns: array_map(static function (DbalColumn $dbalColumn): Column {
                    return Column::createFromDbalColumn($dbalColumn);
                }, $dbalTable->getColumns()),
                indexes: array_values(array_map(static function (DbalIndex $dbalIndex): Index {
                    return Index::createFromDbalIndex($dbalIndex);
                }, $dbalTable->getIndexes()))
            );
        } catch (TableHelperException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw UtilException::databaseTableHelperException(__FUNCTION__, $e);
        }
    }

    /**
     * @param non-empty-string $table
     *
     * @throws TableHelperException
     */
    public static function columnExists(Connection $connection, string $table, string $columnName): bool
    {
        try {
            return self::getSchemaManager($connection)->introspectTableByUnquotedName($table)->hasColumn($columnName);
        } catch (TableHelperException $e) {
            throw $e;
        } catch (TableDoesNotExist) {
            return false;
        } catch (\Throwable $e) {
            throw UtilException::databaseTableHelperException(__FUNCTION__, $e);
        }
    }

    /**
     * @param non-empty-string $table
     *
     * @throws TableHelperException
     */
    public static function getColumnOfTable(Connection $connection, string $table, string $columnName): Column
    {
        try {
            $dbalColumn = self::getSchemaManager($connection)->introspectTableByUnquotedName($table)->getColumn($columnName);

            return Column::createFromDbalColumn($dbalColumn);
        } catch (TableHelperException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw UtilException::databaseTableHelperException(__FUNCTION__, $e);
        }
    }

    /**
     * @param non-empty-string $table
     *
     * @throws TableHelperException
     */
    public static function indexExists(Connection $connection, string $table, string $indexName): bool
    {
        try {
            return self::getSchemaManager($connection)->introspectTableByUnquotedName($table)->hasIndex($indexName);
        } catch (TableHelperException $e) {
            throw $e;
        } catch (TableDoesNotExist) {
            return false;
        } catch (\Throwable $e) {
            throw UtilException::databaseTableHelperException(__FUNCTION__, $e);
        }
    }

    /**
     * @param non-empty-string $table
     *
     * @throws TableHelperException
     */
    public static function getIndexOfTable(Connection $connection, string $table, string $indexName): Index
    {
        try {
            $dbalIndex = self::getSchemaManager($connection)->introspectTableByUnquotedName($table)->getIndex($indexName);

            return Index::createFromDbalIndex($dbalIndex);
        } catch (TableHelperException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw UtilException::databaseTableHelperException(__FUNCTION__, $e);
        }
    }

    /**
     * @param non-empty-string $table
     * @param list<string> $spansColumns
     *
     * @throws TableHelperException
     */
    public static function indexSpansColumns(Connection $connection, string $table, string $indexName, array $spansColumns): bool
    {
        try {
            return self::getSchemaManager($connection)->introspectTableByUnquotedName($table)->getIndex($indexName)->spansColumns($spansColumns);
        } catch (TableHelperException $e) {
            throw $e;
        } catch (TableDoesNotExist) {
            return false;
        } catch (\Throwable $e) {
            throw UtilException::databaseTableHelperException(__FUNCTION__, $e);
        }
    }

    /**
     * @param non-empty-string $table
     *
     * @throws TableHelperException
     */
    public static function foreignKeyExists(Connection $connection, string $table, string $foreignKeyName): bool
    {
        try {
            return self::getSchemaManager($connection)->introspectTableByUnquotedName($table)->hasForeignKey($foreignKeyName);
        } catch (TableHelperException $e) {
            throw $e;
        } catch (TableDoesNotExist) {
            return false;
        } catch (\Throwable $e) {
            throw UtilException::databaseTableHelperException(__FUNCTION__, $e);
        }
    }

    /**
     * Checks if a foreign key exists by column relationships rather than by foreign key name.
     *
     * @param non-empty-string $table
     * @param list<string> $localColumns
     * @param list<string> $foreignColumns
     *
     * @throws TableHelperException
     */
    public static function foreignKeyExistsByColumns(
        Connection $connection,
        string $table,
        array $localColumns,
        string $foreignTable,
        array $foreignColumns
    ): bool {
        try {
            $foreignKeys = self::getSchemaManager($connection)->introspectTableForeignKeyConstraintsByUnquotedName($table);

            foreach ($foreignKeys as $foreignKey) {
                $referencingColumns = array_map(
                    static fn (UnqualifiedName $col): string => $col->getIdentifier()->getValue(),
                    $foreignKey->getReferencingColumnNames()
                );
                $referencedColumns = array_map(
                    static fn (UnqualifiedName $col): string => $col->getIdentifier()->getValue(),
                    $foreignKey->getReferencedColumnNames()
                );
                $referencedTable = $foreignKey->getReferencedTableName()->getUnqualifiedName()->getValue();

                if ($referencingColumns === $localColumns
                    && $referencedTable === $foreignTable
                    && $referencedColumns === $foreignColumns
                ) {
                    return true;
                }
            }

            return false;
        } catch (TableHelperException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw UtilException::databaseTableHelperException(__FUNCTION__, $e);
        }
    }

    /**
     * @param non-empty-string $table
     *
     * @throws TableHelperException
     */
    public static function getForeignKeyOfTable(Connection $connection, string $table, string $foreignKeyName): ForeignKey
    {
        try {
            $dbalForeignKey = self::getSchemaManager($connection)->introspectTableByUnquotedName($table)->getForeignKey($foreignKeyName);

            return new ForeignKey(
                referencingColumnNames: array_map(static function (UnqualifiedName $columnName): string {
                    return $columnName->getIdentifier()->getValue();
                }, $dbalForeignKey->getReferencingColumnNames()),
                referencedTableName: $dbalForeignKey->getReferencedTableName()->getUnqualifiedName()->getValue(),
                referencedColumnNames: array_map(static function (UnqualifiedName $columnName): string {
                    return $columnName->getIdentifier()->getValue();
                }, $dbalForeignKey->getReferencedColumnNames()),
                onDeleteAction: $dbalForeignKey->getOnDeleteAction()->value,
            );
        } catch (TableHelperException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw UtilException::databaseTableHelperException(__FUNCTION__, $e);
        }
    }

    /**
     * @throws TableHelperException
     *
     * @return AbstractSchemaManager<TPlatform>
     */
    private static function getSchemaManager(Connection $connection): AbstractSchemaManager
    {
        try {
            return $connection->createSchemaManager();
        } catch (\Throwable $e) {
            throw UtilException::databaseTableHelperException(__FUNCTION__, $e);
        }
    }
}
