<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\AddColumnTrait;

/**
 * @internal
 */
#[CoversClass(AddColumnTrait::class)]
class AddColumnTraitTest extends TestCase
{
    #[DataProvider('columnExistsScenarios')]
    public function testReturnsFalseIfColumnExists(string $method): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('states');
        $connection->expects($this->never())->method('executeStatement');

        $migration = new TestAddColumnMigration();

        $result = $migration->{$method}($connection, 'product', 'states', 'JSON');

        static::assertFalse($result);
    }

    #[DataProvider('columnDoesNotExistScenarios')]
    public function testExecutesStatementAndReturnsTrueIfColumnDoesNotExist(
        string $method,
        string $table,
        string $column,
        string $type,
        bool $nullable,
        string $default,
        string $expectedSql
    ): void {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with($expectedSql);

        $migration = new TestAddColumnMigration();

        $result = $migration->{$method}($connection, $table, $column, $type, $nullable, $default);

        static::assertTrue($result);
    }

    public static function columnExistsScenarios(): \Generator
    {
        yield 'addColumn' => ['callAddColumn'];
        yield 'addColumnInstant' => ['callAddColumnInstant'];
    }

    public static function columnDoesNotExistScenarios(): \Generator
    {
        yield 'addColumn default nullable' => [
            'callAddColumn',
            'product',
            'states',
            'JSON',
            true,
            'NULL',
            'ALTER TABLE `product` ADD COLUMN `states` JSON NULL DEFAULT NULL;',
        ];

        yield 'addColumn not nullable' => [
            'callAddColumn',
            'product',
            'active',
            'TINYINT(1)',
            false,
            '\'1\'',
            'ALTER TABLE `product` ADD COLUMN `active` TINYINT(1) NOT NULL DEFAULT \'1\';',
        ];

        yield 'addColumnInstant default nullable' => [
            'callAddColumnInstant',
            'product',
            'states',
            'JSON',
            true,
            'NULL',
            'ALTER TABLE `product` ADD COLUMN `states` JSON NULL DEFAULT NULL, ALGORITHM=INSTANT;',
        ];

        yield 'addColumnInstant not nullable' => [
            'callAddColumnInstant',
            'order',
            'priority',
            'INT',
            false,
            '\'0\'',
            'ALTER TABLE `order` ADD COLUMN `priority` INT NOT NULL DEFAULT \'0\', ALGORITHM=INSTANT;',
        ];
    }
}

/**
 * @internal
 */
class TestAddColumnMigration
{
    use AddColumnTrait;

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

    public function callAddColumnInstant(
        Connection $connection,
        string $table,
        string $column,
        string $type,
        bool $nullable = true,
        string $default = 'NULL'
    ): bool {
        return $this->addColumnInstant($connection, $table, $column, $type, $nullable, $default);
    }
}
