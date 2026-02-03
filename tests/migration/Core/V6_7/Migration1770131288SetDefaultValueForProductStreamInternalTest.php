<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1770131288SetDefaultValueForProductStreamInternal;

/**
 * @internal
 */
#[CoversClass(Migration1770131288SetDefaultValueForProductStreamInternal::class)]
class Migration1770131288SetDefaultValueForProductStreamInternalTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1770131288SetDefaultValueForProductStreamInternal();
        static::assertSame(1770131288, $migration->getCreationTimestamp());
    }

    public function testMigrationDoesNothingWhenColumnDoesNotExist(): void
    {
        $columnExistedBefore = TableHelper::columnExists($this->connection, 'product_stream', 'internal');

        if ($columnExistedBefore) {
            $this->connection->executeStatement('ALTER TABLE `product_stream` DROP COLUMN `internal`;');
        }

        static::assertFalse(TableHelper::columnExists($this->connection, 'product_stream', 'internal'));

        $migration = new Migration1770131288SetDefaultValueForProductStreamInternal();
        $migration->update($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'product_stream', 'internal'));

        if ($columnExistedBefore) {
            $this->connection->executeStatement('
                ALTER TABLE `product_stream`
                ADD COLUMN `internal` TINYINT(1) NULL DEFAULT NULL
            ');
        }
    }

    public function testMigrationSetsDefaultValueAndUpdatesNullRecords(): void
    {
        if (!TableHelper::columnExists($this->connection, 'product_stream', 'internal')) {
            $this->connection->executeStatement('
                ALTER TABLE `product_stream`
                ADD COLUMN `internal` TINYINT(1) NULL DEFAULT NULL
            ');
        }

        $this->connection->executeStatement('
            ALTER TABLE `product_stream`
            MODIFY COLUMN `internal` TINYINT(1) NULL
        ');

        $streamId = Uuid::randomBytes();
        $this->connection->insert('product_stream', [
            'id' => $streamId,
            'api_filter' => '[]',
            'invalid' => 0,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $this->connection->executeStatement('
            UPDATE `product_stream`
            SET `internal` = NULL
            WHERE `id` = :id
        ', ['id' => $streamId]);

        $internalValue = $this->connection->fetchOne('
            SELECT `internal`
            FROM `product_stream`
            WHERE `id` = :id
        ', ['id' => $streamId]);
        static::assertNull($internalValue);

        $migration = new Migration1770131288SetDefaultValueForProductStreamInternal();
        $migration->update($this->connection);

        $internalValue = $this->connection->fetchOne('
            SELECT `internal`
            FROM `product_stream`
            WHERE `id` = :id
        ', ['id' => $streamId]);
        static::assertSame('0', $internalValue);

        $migration->update($this->connection);

        $internalValue = $this->connection->fetchOne('
            SELECT `internal`
            FROM `product_stream`
            WHERE `id` = :id
        ', ['id' => $streamId]);
        static::assertSame('0', $internalValue);

        $columnInfo = $this->connection->fetchAssociative("
            SHOW COLUMNS FROM `product_stream` WHERE `Field` = 'internal'
        ");
        static::assertIsArray($columnInfo);
        static::assertSame('NO', $columnInfo['Null']);
        static::assertSame('0', $columnInfo['Default']);

        $this->connection->delete('product_stream', ['id' => $streamId]);
    }

    public function testNewRecordsGetDefaultValue(): void
    {
        if (!TableHelper::columnExists($this->connection, 'product_stream', 'internal')) {
            $this->connection->executeStatement('
                ALTER TABLE `product_stream`
                ADD COLUMN `internal` TINYINT(1) NULL DEFAULT NULL
            ');
        }

        $migration = new Migration1770131288SetDefaultValueForProductStreamInternal();
        $migration->update($this->connection);

        $streamId = Uuid::randomBytes();
        $this->connection->insert('product_stream', [
            'id' => $streamId,
            'api_filter' => '[]',
            'invalid' => 0,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $internalValue = $this->connection->fetchOne('
            SELECT `internal`
            FROM `product_stream`
            WHERE `id` = :id
        ', ['id' => $streamId]);
        static::assertSame('0', $internalValue);

        $this->connection->delete('product_stream', ['id' => $streamId]);
    }
}
