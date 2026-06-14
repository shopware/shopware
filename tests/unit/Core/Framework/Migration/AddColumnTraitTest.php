<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\AddColumnTrait;

/**
 * @internal
 */
#[CoversClass(AddColumnTrait::class)]
class AddColumnTraitTest extends TestCase
{
    public function testReturnsFalseIfColumnExists(): void
    {
        $connection = $this->createConnectionMock(columnExists: true);
        $connection->expects($this->never())->method('executeStatement');

        $migration = new TestAddColumnMigration();

        $result = $migration->callAddColumn($connection, 'product', 'states', 'JSON');

        static::assertFalse($result);
    }

    /**
     * @param non-empty-string $table
     * @param non-empty-string $column
     */
    #[DataProvider('columnDoesNotExistScenarios')]
    public function testUsesInstantAlgorithm(
        string $table,
        string $column,
        string $type,
        bool $nullable,
        string $default,
        string $expectedInstantSql
    ): void {
        $connection = $this->createConnectionMock(columnExists: false);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with($expectedInstantSql);

        $migration = new TestAddColumnMigration();

        $result = $migration->callAddColumn($connection, $table, $column, $type, $nullable, $default);

        static::assertTrue($result);
    }

    public function testFallsBackWhenInstantNotSupported(): void
    {
        $connection = $this->createConnectionMock(columnExists: false);

        $instantSql = 'ALTER TABLE `app` ADD COLUMN `source_config` JSON NOT NULL DEFAULT (JSON_OBJECT()), ALGORITHM=INSTANT;';
        $fallbackSql = 'ALTER TABLE `app` ADD COLUMN `source_config` JSON NOT NULL DEFAULT (JSON_OBJECT());';

        $exception = new class('ALGORITHM=INSTANT is not supported') extends \Exception implements DBALException {};

        $connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use ($instantSql, $fallbackSql, $exception): int {
                if ($sql === $instantSql) {
                    throw $exception;
                }

                static::assertSame($fallbackSql, $sql);

                return 0;
            });

        $migration = new TestAddColumnMigration();

        $result = $migration->callAddColumn($connection, 'app', 'source_config', 'JSON', false, '(JSON_OBJECT())');

        static::assertTrue($result);
    }

    /**
     * @return \Generator<string, array{string, string, string, bool, string, string}>
     */
    public static function columnDoesNotExistScenarios(): \Generator
    {
        yield 'nullable with NULL default' => [
            'product',
            'states',
            'JSON',
            true,
            'NULL',
            'ALTER TABLE `product` ADD COLUMN `states` JSON NULL DEFAULT NULL, ALGORITHM=INSTANT;',
        ];

        yield 'not nullable with explicit default' => [
            'product',
            'active',
            'TINYINT(1)',
            false,
            '\'1\'',
            'ALTER TABLE `product` ADD COLUMN `active` TINYINT(1) NOT NULL DEFAULT \'1\', ALGORITHM=INSTANT;',
        ];

        yield 'not nullable int column' => [
            'order',
            'priority',
            'INT',
            false,
            '\'0\'',
            'ALTER TABLE `order` ADD COLUMN `priority` INT NOT NULL DEFAULT \'0\', ALGORITHM=INSTANT;',
        ];
    }

    /**
     * @return Connection&MockObject
     */
    private function createConnectionMock(bool $columnExists): Connection
    {
        $table = $this->createMock(Table::class);
        $table->method('hasColumn')->willReturn($columnExists);

        $schemaManager = $this->createMock(MySQLSchemaManager::class);
        $schemaManager->method('introspectTableByUnquotedName')->willReturn($table);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        return $connection;
    }
}

/**
 * @internal
 */
class TestAddColumnMigration
{
    use AddColumnTrait;

    /**
     * @param non-empty-string $table
     * @param non-empty-string $column
     */
    public function callAddColumn(
        Connection $connection,
        string $table,
        string $column,
        string $type,
        bool $nullable = true,
        string $default = 'NULL'
    ): bool {
        return $this->addColumn($connection, $table, $column, $type, $nullable, $default);
    }
}
