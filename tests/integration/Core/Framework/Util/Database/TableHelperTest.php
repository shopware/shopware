<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Util\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\Column;
use Shopware\Core\Framework\Util\Database\Index;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Util\Database\TableHelperException;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Tests\Integration\Core\Framework\Util\Database\TableHelper\ExceptionThrowingMiddleware;

/**
 * @internal
 */
class TableHelperTest extends TestCase
{
    use KernelTestBehaviour;

    private const UNKNOWN_NAME = 'foo_bar';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
    }

    public function testTableExists(): void
    {
        static::assertTrue(TableHelper::tableExists($this->connection, ProductDefinition::ENTITY_NAME));
    }

    public function testTableDoesNotExist(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, self::UNKNOWN_NAME));
    }

    public function testTableExistsThrowsUtilExceptionWhileReadingTables(): void
    {
        $connection = MySQLFactory::create([new ExceptionThrowingMiddleware()]);

        $this->expectExceptionObject(UtilException::databaseTableHelperException('tableExists', new \RuntimeException('test')));
        TableHelper::tableExists($connection, ProductDefinition::ENTITY_NAME);
    }

    public function testTableExistsThrowsExceptionWhileGettingSchemaManager(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForInvalidConnection());
        TableHelper::tableExists($this->getInvalidConnection(), ProductDefinition::ENTITY_NAME);
    }

    public function testGetTable(): void
    {
        $table = TableHelper::getTable($this->connection, ProductDefinition::ENTITY_NAME);

        static::assertIsList($table->columns);
        static::assertContainsOnlyInstancesOf(Column::class, $table->columns);

        static::assertIsList($table->indexes);
        static::assertContainsOnlyInstancesOf(Index::class, $table->indexes);
    }

    public function testGetTableFromUnknownTableThrowsException(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForNotExistingTable('getTable'));
        TableHelper::getTable($this->connection, self::UNKNOWN_NAME);
    }

    public function testGetTableThrowsExceptionWhileGettingSchemaManager(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForInvalidConnection());
        TableHelper::getTable($this->getInvalidConnection(), ProductDefinition::ENTITY_NAME);
    }

    public function testColumnExists(): void
    {
        static::assertTrue(TableHelper::columnExists($this->connection, ProductDefinition::ENTITY_NAME, 'id'));
    }

    public function testColumnDoesExist(): void
    {
        static::assertFalse(TableHelper::columnExists($this->connection, ProductDefinition::ENTITY_NAME, self::UNKNOWN_NAME));
    }

    public function testColumnExistsFromUnknownTableReturnsFalse(): void
    {
        static::assertFalse(TableHelper::columnExists($this->connection, self::UNKNOWN_NAME, 'id'));
    }

    public function testColumnExistsThrowsExceptionWhileGettingSchemaManager(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForInvalidConnection());
        TableHelper::columnExists($this->getInvalidConnection(), ProductDefinition::ENTITY_NAME, 'id');
    }

    public function testGetColumnOfTable(): void
    {
        $column = TableHelper::getColumnOfTable($this->connection, ProductDefinition::ENTITY_NAME, 'type');
        static::assertSame(Types::STRING, $column->type);
        static::assertSame(32, $column->length);
        static::assertTrue($column->isNotNull);
        static::assertSame(ProductDefinition::TYPE_PHYSICAL, $column->defaultValue);
    }

    public function testGetColumnFromUnknownTableThrowsException(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForNotExistingTable('getColumnOfTable'));
        TableHelper::getColumnOfTable($this->connection, self::UNKNOWN_NAME, 'id');
    }

    public function testGetColumnThrowsExceptionWhileGettingSchemaManager(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForInvalidConnection());
        TableHelper::getColumnOfTable($this->getInvalidConnection(), ProductDefinition::ENTITY_NAME, self::UNKNOWN_NAME);
    }

    public function testIndexExists(): void
    {
        static::assertTrue(TableHelper::indexExists($this->connection, ProductDefinition::ENTITY_NAME, 'idx.product.type'));
    }

    public function testIndexDoesExist(): void
    {
        static::assertFalse(TableHelper::indexExists($this->connection, ProductDefinition::ENTITY_NAME, self::UNKNOWN_NAME));
    }

    public function testIndexExistsFromUnknownTableReturnsFalse(): void
    {
        static::assertFalse(TableHelper::indexExists($this->connection, self::UNKNOWN_NAME, 'idx.product.type'));
    }

    public function testIndexExistsThrowsExceptionWhileGettingSchemaManager(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForInvalidConnection());
        TableHelper::indexExists($this->getInvalidConnection(), ProductDefinition::ENTITY_NAME, 'idx.product.type');
    }

    public function testGetIndexOfTable(): void
    {
        $index = TableHelper::getIndexOfTable($this->connection, ProductDefinition::ENTITY_NAME, 'idx.product.type');
        static::assertSame(IndexType::REGULAR->name, $index->type);
    }

    public function testGetIndexFromUnknownTableThrowsException(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForNotExistingTable('getIndexOfTable'));
        TableHelper::getIndexOfTable($this->connection, self::UNKNOWN_NAME, 'idx.product.type');
    }

    public function testGetIndexThrowsExceptionWhileGettingSchemaManager(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForInvalidConnection());
        TableHelper::getIndexOfTable($this->getInvalidConnection(), ProductDefinition::ENTITY_NAME, self::UNKNOWN_NAME);
    }

    public function testIndexSpansColumns(): void
    {
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, ProductDefinition::ENTITY_NAME, 'idx.product.type', ['type']));
    }

    public function testIndexDoesNotSpanColumns(): void
    {
        static::assertFalse(TableHelper::indexSpansColumns($this->connection, ProductDefinition::ENTITY_NAME, 'idx.product.type', [self::UNKNOWN_NAME]));
    }

    public function testIndexSpansColumnsFromUnknownTableReturnsFalse(): void
    {
        static::assertFalse(TableHelper::indexSpansColumns($this->connection, self::UNKNOWN_NAME, 'idx.product.type', ['type']));
    }

    public function testIndexSpansColumnsThrowsExceptionWhileGettingSchemaManager(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForInvalidConnection());
        TableHelper::indexSpansColumns($this->getInvalidConnection(), ProductDefinition::ENTITY_NAME, 'idx.product.type', ['type']);
    }

    public function testGetForeignKeyOfTable(): void
    {
        $foreignKey = TableHelper::getForeignKeyOfTable($this->connection, ProductDefinition::ENTITY_NAME, 'fk.product.parent_id');
        static::assertSame(['parent_id', 'parent_version_id'], $foreignKey->referencingColumnNames);
        static::assertSame(ProductDefinition::ENTITY_NAME, $foreignKey->referencedTableName);
        static::assertSame(['id', 'version_id'], $foreignKey->referencedColumnNames);
        static::assertSame(ReferentialAction::CASCADE->value, $foreignKey->onDeleteAction);
    }

    public function testGetForeignKeyOfTableFromUnknownTableThrowsException(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForNotExistingTable('getForeignKeyOfTable'));
        TableHelper::getForeignKeyOfTable($this->connection, self::UNKNOWN_NAME, 'fk.product.parent_id');
    }

    public function testGetForeignKeyOfTableThrowsExceptionWhileGettingSchemaManager(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForInvalidConnection());
        TableHelper::getForeignKeyOfTable($this->getInvalidConnection(), ProductDefinition::ENTITY_NAME, 'fk.product.parent_id');
    }

    public function testForeignKeyExists(): void
    {
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, ProductDefinition::ENTITY_NAME, 'fk.product.parent_id'));
    }

    public function testForeignKeyDoesExist(): void
    {
        static::assertFalse(TableHelper::foreignKeyExists($this->connection, ProductDefinition::ENTITY_NAME, self::UNKNOWN_NAME));
    }

    public function testForeignKeyExistsFromUnknownTableReturnsFalse(): void
    {
        static::assertFalse(TableHelper::foreignKeyExists($this->connection, self::UNKNOWN_NAME, 'fk.product.parent_id'));
    }

    public function testForeignKeyExistsThrowsExceptionWhileGettingSchemaManager(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForInvalidConnection());
        TableHelper::foreignKeyExists($this->getInvalidConnection(), ProductDefinition::ENTITY_NAME, 'fk.product.parent_id');
    }

    /**
     * @param list<string> $localColumns
     * @param list<string> $foreignColumns
     */
    #[DataProvider('foreignKeyExistsByColumnsProvider')]
    public function testForeignKeyExistsByColumns(
        array $localColumns,
        string $foreignTable,
        array $foreignColumns,
        bool $expectedResult
    ): void {
        static::assertSame(
            $expectedResult,
            TableHelper::foreignKeyExistsByColumns(
                $this->connection,
                ProductDefinition::ENTITY_NAME,
                $localColumns,
                $foreignTable,
                $foreignColumns
            )
        );
    }

    /**
     * @return iterable<string, array{list<string>, string, list<string>, bool}>
     */
    public static function foreignKeyExistsByColumnsProvider(): iterable
    {
        yield 'existing FK with columns in definition order' => [
            ['parent_id', 'parent_version_id'],
            ProductDefinition::ENTITY_NAME,
            ['id', 'version_id'],
            true,
        ];

        yield 'reversed local columns do not match' => [
            ['parent_version_id', 'parent_id'],
            ProductDefinition::ENTITY_NAME,
            ['id', 'version_id'],
            false,
        ];

        yield 'reversed foreign columns do not match' => [
            ['parent_id', 'parent_version_id'],
            ProductDefinition::ENTITY_NAME,
            ['version_id', 'id'],
            false,
        ];

        yield 'non-existing FK with unknown local columns' => [
            [self::UNKNOWN_NAME],
            ProductDefinition::ENTITY_NAME,
            ['id'],
            false,
        ];

        yield 'non-existing FK with wrong foreign table' => [
            ['parent_id', 'parent_version_id'],
            self::UNKNOWN_NAME,
            ['id', 'version_id'],
            false,
        ];

        yield 'non-existing FK with partial local columns' => [
            ['parent_id'],
            ProductDefinition::ENTITY_NAME,
            ['id', 'version_id'],
            false,
        ];
    }

    public function testForeignKeyExistsByColumnsThrowsExceptionWhileGettingSchemaManager(): void
    {
        $this->expectExceptionObject($this->createUtilExceptionForInvalidConnection());
        TableHelper::foreignKeyExistsByColumns(
            $this->getInvalidConnection(),
            ProductDefinition::ENTITY_NAME,
            ['parent_id', 'parent_version_id'],
            ProductDefinition::ENTITY_NAME,
            ['id', 'version_id'],
        );
    }

    private function getInvalidConnection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_mysql']);
    }

    private function createUtilExceptionForInvalidConnection(): TableHelperException
    {
        return UtilException::databaseTableHelperException(
            'getSchemaManager',
            new ConnectionException(
                new Exception('SQLSTATE[HY000]'),
                null
            )
        );
    }

    private function createUtilExceptionForNotExistingTable(string $calledMethod): TableHelperException
    {
        return UtilException::databaseTableHelperException($calledMethod, TableDoesNotExist::new(self::UNKNOWN_NAME));
    }
}
