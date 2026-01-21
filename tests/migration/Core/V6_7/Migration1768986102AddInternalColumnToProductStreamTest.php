<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1768986102AddInternalColumnToProductStream;

/**
 * @internal
 */
#[CoversClass(Migration1768986102AddInternalColumnToProductStream::class)]
class Migration1768986102AddInternalColumnToProductStreamTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1768986102AddInternalColumnToProductStream();
        static::assertSame(1768986102, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        $migration = new Migration1768986102AddInternalColumnToProductStream();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $existingColumns = $this->connection->createSchemaManager()->listTableColumns('product_stream');
        static::assertArrayHasKey('internal', $existingColumns);
    }

    private function rollback(): void
    {
        $existingColumns = $this->connection->createSchemaManager()->listTableColumns('product_stream');

        if (\array_key_exists('internal', $existingColumns)) {
            $this->connection->executeStatement('ALTER TABLE `product_stream` DROP COLUMN `internal`;');
        }
    }
}
