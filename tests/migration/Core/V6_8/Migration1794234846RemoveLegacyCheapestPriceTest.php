<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceBlobPostUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1794234846RemoveLegacyCheapestPrice;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1794234846RemoveLegacyCheapestPrice::class)]
class Migration1794234846RemoveLegacyCheapestPriceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('CREATE TEMPORARY TABLE product_migration_test LIKE product');
        $this->connection->executeStatement('ALTER TABLE product_migration_test RENAME TO product');
        $this->connection->executeStatement('CREATE TEMPORARY TABLE system_config_migration_test LIKE system_config');
        $this->connection->executeStatement('ALTER TABLE system_config_migration_test RENAME TO system_config');
        if (!TableHelper::columnExists($this->connection, 'product', 'cheapest_price')) {
            $this->connection->executeStatement('ALTER TABLE product ADD COLUMN cheapest_price LONGTEXT NULL');
        }
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TEMPORARY TABLE product');
        $this->connection->executeStatement('DROP TEMPORARY TABLE system_config');
    }

    public function testSchedulesTheRepairIdempotently(): void
    {
        $migration = new Migration1794234846RemoveLegacyCheapestPrice();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertArrayHasKey(CheapestPriceBlobPostUpdater::NAME, (new IndexerQueuer($this->connection))->getIndexers());
    }

    public function testDropsLegacyColumnWithoutRequiringReconciliation(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO product (id, version_id, product_number, stock, created_at) VALUES (:id, :version, \'migration-test\', 0, NOW())',
            ['id' => Uuid::randomBytes(), 'version' => Uuid::randomBytes()]
        );
        $migration = new Migration1794234846RemoveLegacyCheapestPrice();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse($this->connection->fetchOne('SHOW COLUMNS FROM product WHERE Field = \'cheapest_price\''));
        static::assertSame('cheapest_price_blob', $this->connection->fetchOne('SHOW COLUMNS FROM product WHERE Field = \'cheapest_price_blob\''));
    }
}
