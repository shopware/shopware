<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1772098113AddEnhancedConversionsToAnalytics;

/**
 * @internal
 */
#[CoversClass(Migration1772098113AddEnhancedConversionsToAnalytics::class)]
class Migration1772098113AddEnhancedConversionsToAnalyticsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1772098113AddEnhancedConversionsToAnalytics();
        static::assertSame(1772098113, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();

        $migration = new Migration1772098113AddEnhancedConversionsToAnalytics();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_analytics', 'enhanced_conversions'));
    }

    private function rollback(): void
    {
        if (TableHelper::columnExists($this->connection, 'sales_channel_analytics', 'enhanced_conversions')) {
            $this->connection->executeStatement('ALTER TABLE `sales_channel_analytics` DROP COLUMN `enhanced_conversions`;');
        }
    }
}
