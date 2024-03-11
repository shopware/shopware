<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class MigrationStepTest extends TestCase
{
    use KernelTestBehaviour;

    public function testDropForeignKey(): void
    {
        $connection = $this->getConnection();

        $this->getStep()->dropTableIfExists($connection, 'test_table');

        $this->getConnection()->executeStatement(<<<'SQL'
CREATE TABLE `test_table` (
    `id` BINARY(16) NOT NULL,
    `user_id` BINARY(16) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `test_table`
    ADD CONSTRAINT `fk.test_table.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);
SQL);

        // table does not exists
        static::assertFalse($this->getStep()->dropForeignKeyIfExists($connection, 'test', 'test'));
        // column does not exists
        static::assertFalse($this->getStep()->dropForeignKeyIfExists($connection, 'user', 'test'));

        static::assertTrue($this->getStep()->dropForeignKeyIfExists($connection, 'test_table', 'fk.test_table.user_id'));

        $this->getStep()->dropTableIfExists($connection, 'test_table');
    }

    public function testDropIndex(): void
    {
        $connection = $this->getConnection();

        $this->getStep()->dropTableIfExists($connection, 'test_table');

        $this->getConnection()->executeStatement(<<<'SQL'
CREATE TABLE `test_table` (
    `id` BINARY(16) NOT NULL,
    `user_id` BINARY(16) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `test_table`
    ADD INDEX `idx.user_id` (`user_id`);
SQL);

        // table does not exists
        static::assertFalse($this->getStep()->dropIndexIfExists($connection, 'test', 'test'));
        // column does not exists
        static::assertFalse($this->getStep()->dropIndexIfExists($connection, 'user', 'test'));

        static::assertTrue($this->getStep()->dropIndexIfExists($connection, 'test_table', 'idx.user_id'));

        $this->getStep()->dropTableIfExists($connection, 'test_table');
    }

    public function getStep(): ExampleStep
    {
        return new ExampleStep();
    }

    public function getConnection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}

/**
 * @internal
 */
class ExampleStep extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return time();
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    // @phpstan-ignore-next-line
    public function dropTableIfExists(Connection $connection, string $table): void
    {
        parent::dropTableIfExists($connection, $table);
    }

    // @phpstan-ignore-next-line
    public function dropForeignKeyIfExists(Connection $connection, string $table, string $column): bool
    {
        return parent::dropForeignKeyIfExists($connection, $table, $column);
    }

    // @phpstan-ignore-next-line
    public function dropIndexIfExists(Connection $connection, string $table, string $index): bool
    {
        return parent::dropIndexIfExists($connection, $table, $index);
    }
}
