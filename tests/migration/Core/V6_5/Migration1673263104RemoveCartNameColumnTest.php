<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1673263104RemoveCartNameColumn;

/**
 * @internal
 */
#[CoversClass(Migration1673263104RemoveCartNameColumn::class)]
class Migration1673263104RemoveCartNameColumnTest extends TestCase
{
    private Connection $connection;

    private static string $isCartNameNullable = <<<'SQL'
        SELECT is_nullable
        FROM information_schema.columns
        WHERE table_schema = ?
        AND table_name = 'cart'
        AND column_name = 'name';
    SQL;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        if ($this->connection->fetchOne('SHOW COLUMNS FROM `cart` LIKE \'name\'') !== 'name') {
            $this->connection->executeStatement(
                'ALTER TABLE `cart` ADD COLUMN `name` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL'
            );
        }

        if ($this->connection->fetchOne(self::$isCartNameNullable, [$this->connection->getDatabase()]) === 'YES') {
            $this->connection->executeStatement(
                'ALTER TABLE `cart` CHANGE `name` `name` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL'
            );
        }
    }

    public function testMigrate(): void
    {
        static::assertEquals(
            'NO',
            $this->connection->fetchOne(self::$isCartNameNullable, [$this->connection->getDatabase()])
        );

        $migration = new Migration1673263104RemoveCartNameColumn();
        $migration->update($this->connection);

        static::assertEquals(
            'YES',
            $this->connection->fetchOne(self::$isCartNameNullable, [$this->connection->getDatabase()])
        );

        $migration = new Migration1673263104RemoveCartNameColumn();
        $migration->update($this->connection);

        static::assertEquals(
            'YES',
            $this->connection->fetchOne(self::$isCartNameNullable, [$this->connection->getDatabase()])
        );
    }

    public function testMigrateDestructive(): void
    {
        $field = $this->connection->fetchOne('SHOW COLUMNS FROM `cart` LIKE \'name\'');

        static::assertEquals('name', $field);

        $migration = new Migration1673263104RemoveCartNameColumn();
        $migration->updateDestructive($this->connection);

        $field = $this->connection->fetchOne('SHOW COLUMNS FROM `cart` LIKE \'name\'');
        static::assertFalse($field);

        $migration = new Migration1673263104RemoveCartNameColumn();
        $migration->updateDestructive($this->connection);
        static::assertFalse($field);
    }
}
