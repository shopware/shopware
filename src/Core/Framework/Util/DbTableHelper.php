<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column as DbalColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\Type;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\DbTableHelper\Column;
use Shopware\Core\Framework\Util\DbTableHelper\ForeignKey;
use Shopware\Core\Framework\Util\DbTableHelper\Table;

/**
 * @final
 *
 * @template TPlatform of AbstractPlatform
 */
#[Package('framework')]
class DbTableHelper
{
    /**
     * @var AbstractSchemaManager<TPlatform>|null
     */
    private static ?AbstractSchemaManager $schemaManager = null;

    private function __construct()
    {
    }

    public static function tableExists(Connection $connection, string $tableName): bool
    {
        return self::getSchemaManager($connection)->tableExists($tableName);
    }

    public static function getTable(Connection $connection, string $tableName): Table
    {
        $dbalTable = self::getSchemaManager($connection)->introspectTable($tableName);

        return new Table(
            columnNames: array_map(static function (DbalColumn $column): string {
                return $column->getObjectName()->getIdentifier()->getValue();
            }, $dbalTable->getColumns())
        );
    }

    /**
     * @param non-empty-string $table
     */
    public static function columnExists(Connection $connection, string $table, string $columnName): bool
    {
        return self::getSchemaManager($connection)->introspectTable($table)->hasColumn($columnName);
    }

    /**
     * @param non-empty-string $table
     */
    public static function getColumnOfTable(Connection $connection, string $table, string $columnName): Column
    {
        $dbalColumn = self::getSchemaManager($connection)->introspectTable($table)->getColumn($columnName);

        return new Column(
            type: Type::lookupName($dbalColumn->getType()),
            length: $dbalColumn->getLength(),
            isNotNull: $dbalColumn->getNotnull(),
            defaultValue: $dbalColumn->getDefault(),
        );
    }

    /**
     * @param non-empty-string $table
     */
    public static function indexExists(Connection $connection, string $table, string $indexName): bool
    {
        return self::getSchemaManager($connection)->introspectTable($table)->hasIndex($indexName);
    }

    /**
     * @param non-empty-string $table
     * @param list<string> $spansColumns
     */
    public static function indexSpansColumns(Connection $connection, string $table, string $indexName, array $spansColumns): bool
    {
        return self::getSchemaManager($connection)->introspectTable($table)->getIndex($indexName)->spansColumns($spansColumns);
    }

    /**
     * @param non-empty-string $table
     */
    public static function getForeignKeyOfTable(Connection $connection, string $table, string $foreignKeyName): ForeignKey
    {
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
    }

    /**
     * @return AbstractSchemaManager<TPlatform>
     */
    private static function getSchemaManager(Connection $connection): AbstractSchemaManager
    {
        if (self::$schemaManager !== null) {
            return self::$schemaManager;
        }

        self::$schemaManager = $connection->createSchemaManager();

        return self::$schemaManager;
    }
}
