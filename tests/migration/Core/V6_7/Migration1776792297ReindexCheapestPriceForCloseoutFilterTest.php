<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1776792297ReindexCheapestPriceForCloseoutFilter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1776792297ReindexCheapestPriceForCloseoutFilter::class)]
class Migration1776792297ReindexCheapestPriceForCloseoutFilterTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1776792297, (new Migration1776792297ReindexCheapestPriceForCloseoutFilter())->getCreationTimestamp());
    }

    public function testCheapestPriceIndexingIsRegistered(): void
    {
        $migration = new Migration1776792297ReindexCheapestPriceForCloseoutFilter();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();

        static::assertArrayHasKey('product.indexer', $indexers);
        static::assertContains('product.cheapest-price', $indexers['product.indexer']);
    }
}
