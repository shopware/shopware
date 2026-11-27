<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1794836992RebuildCheapestPriceSalesChannelIds;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1794836992RebuildCheapestPriceSalesChannelIds::class)]
class Migration1794836992RebuildCheapestPriceSalesChannelIdsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->beginTransaction();
        $this->connection->delete('system_config', ['configuration_key' => IndexerQueuer::INDEXER_KEY]);
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();

        parent::tearDown();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1794836992, (new Migration1794836992RebuildCheapestPriceSalesChannelIds())->getCreationTimestamp());
    }

    public function testOnlyTheCheapestPriceUpdaterOfTheProductIndexerIsRegistered(): void
    {
        $migration = new Migration1794836992RebuildCheapestPriceSalesChannelIds();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();

        static::assertSame(['product.indexer' => [ProductIndexer::CHEAPEST_PRICE_UPDATER]], $indexers);
    }
}
