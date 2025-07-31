<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
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
        $connection = $this->getContainer()->get(Connection::class);

        $this->revertMigration($connection);

        $migration = new Migration1753191228AddMediaThumbnailSizeIdToMediaThumbnail();
        $migration->update($connection);
        $migration->update($connection);

        $manager = $connection->createSchemaManager();
        $columns = $manager->listTableColumns('media_thumbnail');

        static::assertArrayHasKey('media_thumbnail_size_id', $columns);
        static::assertTrue($columns['media_thumbnail_size_id']->getNotnull());
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1753191228, (new Migration1753191228AddMediaThumbnailSizeIdToMediaThumbnail())->getCreationTimestamp());
    }

    private function revertMigration(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `media_thumbnail` DROP FOREIGN KEY `fk.media_thumbnail.media_thumbnail_size_id`');
        $connection->executeStatement('ALTER TABLE `media_thumbnail` DROP COLUMN `media_thumbnail_size_id`');
    }
}
