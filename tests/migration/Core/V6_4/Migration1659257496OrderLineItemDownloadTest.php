<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1659257496OrderLineItemDownload;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1659257496OrderLineItemDownload
 */
class Migration1659257496OrderLineItemDownloadTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->prepare();
    }

    public function testMigration(): void
    {
        $migration = new Migration1659257496OrderLineItemDownload();
        $migration->update($this->connection);
        // test if can be executed multiple times
        $migration->update($this->connection);

        $flowSequenceColumns = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM order_line_item_download'), 'Field');

        static::assertContains('id', $flowSequenceColumns);
        static::assertContains('version_id', $flowSequenceColumns);
        static::assertContains('order_line_item_id', $flowSequenceColumns);
        static::assertContains('order_line_item_version_id', $flowSequenceColumns);
        static::assertContains('position', $flowSequenceColumns);
        static::assertContains('access_granted', $flowSequenceColumns);
        static::assertContains('media_id', $flowSequenceColumns);
        static::assertContains('custom_fields', $flowSequenceColumns);
        static::assertContains('created_at', $flowSequenceColumns);
        static::assertContains('updated_at', $flowSequenceColumns);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'order_line_item', 'states'));
    }

    private function prepare(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `order_line_item_download`');

        if (EntityDefinitionQueryHelper::columnExists($this->connection, 'order_line_item', 'states')) {
            $this->connection->executeStatement('ALTER TABLE `order_line_item` DROP COLUMN `states`');
        }
    }
}
