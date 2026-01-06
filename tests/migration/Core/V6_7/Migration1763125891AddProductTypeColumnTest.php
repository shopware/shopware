<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1763125891AddProductTypeColumn;

/**
 * @internal
 */
#[CoversClass(Migration1763125891AddProductTypeColumn::class)]
class Migration1763125891AddProductTypeColumnTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdateAddsTypeColumnAndIndex(): void
    {
        $this->ensureStatesColumnExists();
        $this->dropTypeColumnIfExists();

        $migration = new Migration1763125891AddProductTypeColumn();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $table = $this->getProductTable();

        static::assertTrue($table->hasColumn('type'));
        static::assertSame('physical', $table->getColumn('type')->getDefault());
        static::assertTrue($table->hasIndex('idx.product.type'));
    }

    private function dropTypeColumnIfExists(): void
    {
        $table = $this->getProductTable();

        if ($table->hasIndex('idx.product.type')) {
            $this->connection->executeStatement('DROP INDEX `idx.product.type` ON `product`');
        }

        if ($table->hasColumn('type')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `type`');
        }
    }

    private function ensureStatesColumnExists(): void
    {
        $table = $this->getProductTable();

        if ($table->hasColumn('states')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `states` JSON NULL');
    }

    private function getProductTable(): Table
    {
        return $this->connection->createSchemaManager()->introspectTable('product');
    }
}
