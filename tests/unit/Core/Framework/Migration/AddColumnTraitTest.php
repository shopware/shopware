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
    public function testReturnsFalseIfColumnExists(bool $useInstant): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $migration = new TestAddColumnMigration(columnExists: true);

        $result = $useInstant
            ? $migration->callAddColumnInstant($connection, 'product', 'states', 'JSON')
            : $migration->callAddColumn($connection, 'product', 'states', 'JSON');

        static::assertFalse($result);
    }

    #[DataProvider('columnDoesNotExistScenarios')]
    public function testExecutesStatementAndReturnsTrueIfColumnDoesNotExist(
        bool $useInstant,
        string $table,
        string $column,
        string $type,
        bool $nullable,
        string $default,
        string $expectedSql
    ): void {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with($expectedSql);

        $migration = new TestAddColumnMigration(columnExists: false);

        \assert($table !== '');
        \assert($column !== '');

        $result = $useInstant
            ? $migration->callAddColumnInstant($connection, $table, $column, $type, $nullable, $default)
            : $migration->callAddColumn($connection, $table, $column, $type, $nullable, $default);

        static::assertTrue($result);
    }

    /**
     * @return \Generator<string, array{bool}>
     */
    public static function columnExistsScenarios(): \Generator
    {
        yield 'addColumn' => [false];
        yield 'addColumnInstant' => [true];
    }

    /**
     * @return \Generator<string, array{bool, string, string, string, bool, string, string}>
     */
    public static function columnDoesNotExistScenarios(): \Generator
    {
        yield 'addColumn default nullable' => [
            false,
            'product',
            'states',
            'JSON',
            true,
            'NULL',
            'ALTER TABLE `product` ADD COLUMN `states` JSON NULL DEFAULT NULL;',
        ];

        yield 'addColumn not nullable' => [
            false,
            'product',
            'active',
            'TINYINT(1)',
            false,
            '\'1\'',
            'ALTER TABLE `product` ADD COLUMN `active` TINYINT(1) NOT NULL DEFAULT \'1\';',
        ];

        yield 'addColumnInstant default nullable' => [
            true,
            'product',
            'states',
            'JSON',
            true,
            'NULL',
            'ALTER TABLE `product` ADD COLUMN `states` JSON NULL DEFAULT NULL, ALGORITHM=INSTANT;',
        ];

        yield 'addColumnInstant not nullable' => [
            true,
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

    public function __construct(
        private readonly bool $columnExists = false
    ) {
    }

    protected function columnExists(Connection $connection, string $table, string $column): bool
    {
        return $this->columnExists;
    }

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

    /**
     * @param non-empty-string $table
     * @param non-empty-string $column
     */
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
