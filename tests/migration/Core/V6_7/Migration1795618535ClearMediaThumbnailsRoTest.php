<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1795618535ClearMediaThumbnailsRo;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1795618535ClearMediaThumbnailsRo::class)]
class Migration1795618535ClearMediaThumbnailsRoTest extends TestCase
{
    public function testUpdateClearsThumbnailBlobsAndKeepsMediaRows(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $hasColumn = TableHelper::columnExists($connection, 'media', 'thumbnails_ro');
        if (!$hasColumn) {
            $connection->executeStatement('ALTER TABLE media ADD thumbnails_ro LONGBLOB NULL');
        }

        $connection->beginTransaction();
        try {
            $fileName = Uuid::randomHex();
            $queue = new MultiInsertQueryQueue($connection);
            for ($i = 0; $i < 1002; ++$i) {
                $queue->addInsert('media', [
                    'id' => Uuid::randomBytes(),
                    'file_name' => $fileName,
                    'created_at' => '2026-01-01 00:00:00',
                    'thumbnails_ro' => $i === 0 ? null : 'legacy thumbnail data',
                ]);
            }
            $queue->execute();

            $migration = new Migration1795618535ClearMediaThumbnailsRo();
            static::assertSame(1001, (int) $connection->fetchOne('SELECT COUNT(thumbnails_ro) FROM media WHERE file_name = ?', [$fileName]));

            $migration->update($connection);
            $migration->update($connection);
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(thumbnails_ro) FROM media WHERE file_name = ?', [$fileName]));
            static::assertSame(1002, (int) $connection->fetchOne('SELECT COUNT(*) FROM media WHERE file_name = ?', [$fileName]));
        } finally {
            $connection->rollBack();
            if (!$hasColumn) {
                $connection->executeStatement('ALTER TABLE media DROP COLUMN thumbnails_ro');
            }
        }
    }
}
