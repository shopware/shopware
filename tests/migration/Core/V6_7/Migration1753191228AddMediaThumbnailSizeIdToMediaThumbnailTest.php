<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1753191228AddMediaThumbnailSizeIdToMediaThumbnail;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1753191228AddMediaThumbnailSizeIdToMediaThumbnail::class)]
class Migration1753191228AddMediaThumbnailSizeIdToMediaThumbnailTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $this->revertMigration($connection);

        $migration = new Migration1753191228AddMediaThumbnailSizeIdToMediaThumbnail();
        $migration->update($connection);
        $migration->update($connection);

        $sizeIdColumn = TableHelper::getColumnOfTable($connection, 'media_thumbnail', 'media_thumbnail_size_id');
        static::assertFalse($sizeIdColumn->isNotNull);
    }

    public function testDestructiveMigration(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $this->revertDestructiveMigration($connection);

        $migration = new Migration1753191228AddMediaThumbnailSizeIdToMediaThumbnail();
        $migration->updateDestructive($connection);
        $migration->updateDestructive($connection);

        $sizeIdColumn = TableHelper::getColumnOfTable($connection, 'media_thumbnail', 'media_thumbnail_size_id');
        static::assertTrue($sizeIdColumn->isNotNull);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1753191228, (new Migration1753191228AddMediaThumbnailSizeIdToMediaThumbnail())->getCreationTimestamp());
    }

    private function revertMigration(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'media_thumbnail', 'media_thumbnail_size_id')) {
            $connection->executeStatement('ALTER TABLE `media_thumbnail` DROP FOREIGN KEY `fk.media_thumbnail.media_thumbnail_size_id`');
            $connection->executeStatement('ALTER TABLE `media_thumbnail` DROP COLUMN `media_thumbnail_size_id`');
        }
    }

    private function revertDestructiveMigration(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'media_thumbnail', 'media_thumbnail_size_id')) {
            $connection->executeStatement('ALTER TABLE `media_thumbnail` DROP FOREIGN KEY `fk.media_thumbnail.media_thumbnail_size_id`');
        }
        $connection->executeStatement('
            ALTER TABLE `media_thumbnail`
            MODIFY COLUMN `media_thumbnail_size_id` BINARY(16) NULL
        ');
        $connection->executeStatement('
            ALTER TABLE `media_thumbnail`
            ADD CONSTRAINT `fk.media_thumbnail.media_thumbnail_size_id`
            FOREIGN KEY (`media_thumbnail_size_id`)
            REFERENCES `media_thumbnail_size` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE
        ');
    }
}
