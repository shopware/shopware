<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1795517143AddContentLayoutMediaUsage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1795517143AddContentLayoutMediaUsage::class)]
class Migration1795517143AddContentLayoutMediaUsageTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `content_layout_media_usage`');
    }

    protected function tearDown(): void
    {
        (new Migration1795517143AddContentLayoutMediaUsage())->update($this->connection);
        (new IndexerQueuer($this->connection))->finishIndexer(['content_layout_media_usage.indexer']);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1795517143, (new Migration1795517143AddContentLayoutMediaUsage())->getCreationTimestamp());
    }

    public function testCreatesTheTableAndIsIdempotent(): void
    {
        $migration = new Migration1795517143AddContentLayoutMediaUsage();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'content_layout_media_usage'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'content_layout_media_usage', 'fk.content_layout_media_usage.content_layout_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'content_layout_media_usage', 'fk.content_layout_media_usage.media_id'));
    }

    public function testRegistersTheIndexerFillingTheTable(): void
    {
        (new Migration1795517143AddContentLayoutMediaUsage())->update($this->connection);

        static::assertArrayHasKey('content_layout_media_usage.indexer', (new IndexerQueuer($this->connection))->getIndexers());
    }
}
