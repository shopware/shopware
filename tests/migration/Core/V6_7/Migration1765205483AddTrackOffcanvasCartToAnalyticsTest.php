<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1765205483AddTrackOffcanvasCartToAnalytics;

/**
 * @internal
 */
#[CoversClass(Migration1765205483AddTrackOffcanvasCartToAnalytics::class)]
class Migration1765205483AddTrackOffcanvasCartToAnalyticsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1765205483AddTrackOffcanvasCartToAnalytics();
        static::assertSame(1765205483, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        $migration = new Migration1765205483AddTrackOffcanvasCartToAnalytics();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $existingColumns = $this->connection->createSchemaManager()->listTableColumns('sales_channel_analytics');
        static::assertArrayHasKey('track_offcanvas_cart', $existingColumns);
    }

    private function rollback(): void
    {
        $existingColumns = $this->connection->createSchemaManager()->listTableColumns('sales_channel_analytics');

        if (\array_key_exists('track_offcanvas_cart', $existingColumns)) {
            $this->connection->executeStatement('ALTER TABLE `sales_channel_analytics` DROP COLUMN `track_offcanvas_cart`;');
        }
    }
}
