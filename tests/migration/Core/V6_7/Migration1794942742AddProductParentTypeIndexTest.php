<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1794942742AddProductParentTypeIndex;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1794942742AddProductParentTypeIndex::class)]
class Migration1794942742AddProductParentTypeIndexTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        if (!TableHelper::indexExists($this->connection, 'product', 'fk.product.parent_id')) {
            $this->connection->executeStatement('CREATE INDEX `fk.product.parent_id` ON `product` (`parent_id`, `parent_version_id`)');
        }

        if (TableHelper::indexExists($this->connection, 'product', 'idx.product.parent_type')) {
            $this->connection->executeStatement('DROP INDEX `idx.product.parent_type` ON `product`');
        }
    }

    protected function tearDown(): void
    {
        (new Migration1794942742AddProductParentTypeIndex())->update($this->connection);
    }

    public function testCreatesCoveringIndexIdempotently(): void
    {
        $migration = new Migration1794942742AddProductParentTypeIndex();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $columns = $this->connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index ORDER BY SEQ_IN_INDEX',
            ['table' => 'product', 'index' => 'idx.product.parent_type']
        );

        static::assertSame(['parent_id', 'parent_version_id', 'type'], $columns);
    }
}
