<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_8\Migration1763125892RemoveProductStatesColumn;

/**
 * @internal
 */
#[CoversClass(Migration1763125892RemoveProductStatesColumn::class)]
class Migration1763125892RemoveProductStatesColumnTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testRuleIndexerIsRegistered(): void
    {
        $migration = new Migration1763125892RemoveProductStatesColumn();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();

        static::assertArrayHasKey('rule.indexer', $indexers);
    }

    public function testUpdateDestructiveDropsStatesColumn(): void
    {
        $this->addStatesColumn();

        $migration = new Migration1763125892RemoveProductStatesColumn();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        $table = $this->getProductTable();

        static::assertFalse($table->hasColumn('states'));
    }

    private function addStatesColumn(): void
    {
        $table = $this->getProductTable();

        if ($table->hasColumn('states')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `states` JSON NULL');
        $this->connection->executeStatement('ALTER TABLE `product` ADD CONSTRAINT `json.product.states` CHECK (JSON_VALID(`states`))');
    }

    private function getProductTable(): Table
    {
        return $this->connection->createSchemaManager()->introspectTable('product');
    }
}
