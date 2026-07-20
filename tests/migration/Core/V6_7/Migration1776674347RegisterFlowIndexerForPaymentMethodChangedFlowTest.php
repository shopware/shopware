<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1776674347RegisterFlowIndexerForPaymentMethodChangedFlow;

/**
 * @internal
 */
#[CoversClass(Migration1776674347RegisterFlowIndexerForPaymentMethodChangedFlow::class)]
class Migration1776674347RegisterFlowIndexerForPaymentMethodChangedFlowTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1776674347, (new Migration1776674347RegisterFlowIndexerForPaymentMethodChangedFlow())->getCreationTimestamp());
    }

    public function testFlowIndexerIsRegistered(): void
    {
        $migration = new Migration1776674347RegisterFlowIndexerForPaymentMethodChangedFlow();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();

        static::assertArrayHasKey('flow.indexer', $indexers);
    }
}
