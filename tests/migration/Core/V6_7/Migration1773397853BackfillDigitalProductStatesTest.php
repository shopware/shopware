<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1773397853BackfillDigitalProductStates;

/**
 * @internal
 */
#[CoversClass(Migration1773397853BackfillDigitalProductStates::class)]
class Migration1773397853BackfillDigitalProductStatesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->delete('system_config', ['configuration_key' => IndexerQueuer::INDEXER_KEY]);
    }

    protected function tearDown(): void
    {
        $this->connection->delete('system_config', ['configuration_key' => IndexerQueuer::INDEXER_KEY]);

        parent::tearDown();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773397853, (new Migration1773397853BackfillDigitalProductStates())->getCreationTimestamp());
    }

    public function testMigrationRegistersProductIndexer(): void
    {
        $migration = new Migration1773397853BackfillDigitalProductStates();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(
            ['product.indexer' => []],
            (new IndexerQueuer($this->connection))->getIndexers()
        );
    }
}
