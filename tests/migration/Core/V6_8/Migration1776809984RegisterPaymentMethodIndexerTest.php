<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_8\Migration1776809984RegisterPaymentMethodIndexer;

/**
 * @internal
 */
#[CoversClass(Migration1776809984RegisterPaymentMethodIndexer::class)]
class Migration1776809984RegisterPaymentMethodIndexerTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1776809984RegisterPaymentMethodIndexer();

        static::assertSame(1776809984, $migration->getCreationTimestamp());
    }

    public function testPaymentMethodIndexerIsRegistered(): void
    {
        $migration = new Migration1776809984RegisterPaymentMethodIndexer();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();

        static::assertArrayHasKey('payment_method.indexer', $indexers);
    }
}
