<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1779176196AddFeedLabelToProductExport;

/**
 * @internal
 */
#[CoversClass(Migration1779176196AddFeedLabelToProductExport::class)]
class Migration1779176196AddFeedLabelToProductExportTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779176196, (new Migration1779176196AddFeedLabelToProductExport())->getCreationTimestamp());
    }

    public function testMigrationAddsFeedLabelColumnIdempotently(): void
    {
        $this->dropFeedLabelColumn();

        static::assertFalse(TableHelper::columnExists($this->connection, 'product_export', 'feed_label'));

        $migration = new Migration1779176196AddFeedLabelToProductExport();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product_export', 'feed_label'));
    }

    private function dropFeedLabelColumn(): void
    {
        try {
            $this->connection->executeStatement('ALTER TABLE `product_export` DROP COLUMN `feed_label`;');
        } catch (\Throwable) {
        }
    }
}
