<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1795618535ClearMediaThumbnailsRo;
use Shopware\Core\Migration\V6_8\Migration1795594799RemoveMediaThumbnailsRo;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1795594799RemoveMediaThumbnailsRo::class)]
class Migration1795594799RemoveMediaThumbnailsRoTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1795594799, (new Migration1795594799RemoveMediaThumbnailsRo())->getCreationTimestamp());
    }

    public function testUpdateDestructiveRemovesThumbnailsRoColumn(): void
    {
        $this->ensureThumbnailsRoColumnExists();

        $migration = new Migration1795594799RemoveMediaThumbnailsRo();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);
        (new Migration1795618535ClearMediaThumbnailsRo())->update($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'media', 'thumbnails_ro'));
    }

    private function ensureThumbnailsRoColumnExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'media', 'thumbnails_ro')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `media` ADD COLUMN `thumbnails_ro` LONGBLOB NULL');
    }
}
