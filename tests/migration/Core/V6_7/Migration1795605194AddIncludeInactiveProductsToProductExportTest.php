<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1795605194AddIncludeInactiveProductsToProductExport;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1795605194AddIncludeInactiveProductsToProductExport::class)]
class Migration1795605194AddIncludeInactiveProductsToProductExportTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1795605194, (new Migration1795605194AddIncludeInactiveProductsToProductExport())->getCreationTimestamp());
    }

    public function testMigrationAddsIncludeInactiveProductsColumnIdempotently(): void
    {
        $this->dropIncludeInactiveProductsColumn();

        static::assertFalse(TableHelper::columnExists($this->connection, 'product_export', 'include_inactive_products'));

        $migration = new Migration1795605194AddIncludeInactiveProductsToProductExport();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = $this->connection->fetchAssociative(
            'SHOW COLUMNS FROM `product_export` WHERE `Field` = :field',
            ['field' => 'include_inactive_products']
        );
        static::assertIsArray($column);
        static::assertSame('NO', $column['Null']);
        static::assertSame('0', $column['Default']);
    }

    private function dropIncludeInactiveProductsColumn(): void
    {
        try {
            $this->connection->executeStatement('ALTER TABLE `product_export` DROP COLUMN `include_inactive_products`;');
        } catch (\Throwable) {
        }
    }
}
