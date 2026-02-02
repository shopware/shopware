<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column as DbalColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\Type;
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
    /**
     * @var AbstractSchemaManager<TPlatform>|null
     */
    private static ?AbstractSchemaManager $schemaManager = null;

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
     * @throws TableHelperException
     */
    public static function getTable(Connection $connection, string $tableName): Table
    {
        try {
            $dbalTable = self::getSchemaManager($connection)->introspectTable($tableName);

            return new Table(
                columnNames: array_map(static function (DbalColumn $column): string {
                    return $column->getObjectName()->getIdentifier()->getValue();
                }, $dbalTable->getColumns())
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
            return self::getSchemaManager($connection)->introspectTable($table)->hasColumn($columnName);
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
    public static function getColumnOfTable(Connection $connection, string $table, string $columnName): Column
    {
        try {
            $dbalColumn = self::getSchemaManager($connection)->introspectTable($table)->getColumn($columnName);

            return new Column(
                type: Type::lookupName($dbalColumn->getType()),
                length: $dbalColumn->getLength(),
                isNotNull: $dbalColumn->getNotnull(),
                defaultValue: $dbalColumn->getDefault(),
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
    public static function indexExists(Connection $connection, string $table, string $indexName): bool
    {
        try {
            return self::getSchemaManager($connection)->introspectTable($table)->hasIndex($indexName);
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
            return self::getSchemaManager($connection)->introspectTable($table)->getIndex($indexName)->spansColumns($spansColumns);
        } catch (TableHelperException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw UtilException::databaseTableHelperException(__FUNCTION__, $e);
        }
    }

    public static function foreignKeyExists(Connection $connection, string $table, string $foreignKeyName): bool
    {
        try {
            return self::getSchemaManager($connection)->introspectTable($table)->hasForeignKey($foreignKeyName);
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
            $dbalForeignKey = self::getSchemaManager($connection)->introspectTable($table)->getForeignKey($foreignKeyName);

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

    public static function resetSchemaManager(): void
    {
        self::$schemaManager = null;
    }

    /**
     * @throws TableHelperException
     *
     * @return AbstractSchemaManager<TPlatform>
     */
    private static function getSchemaManager(Connection $connection): AbstractSchemaManager
    {
        if (self::$schemaManager !== null) {
            return self::$schemaManager;
        }

        try {
            self::$schemaManager = $connection->createSchemaManager();
        } catch (\Throwable $e) {
            throw UtilException::databaseTableHelperException(__FUNCTION__, $e);
        }

        return self::$schemaManager;
    }
}
