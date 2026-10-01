<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1780325388AddMediaFolderCreatedAtIndex;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1780325388AddMediaFolderCreatedAtIndex::class)]
class Migration1780325388AddMediaFolderCreatedAtIndexTest extends TestCase
{
    private const INDEX_NAME = 'idx.media.media_folder_id_created_at_id';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1780325388, (new Migration1780325388AddMediaFolderCreatedAtIndex())->getCreationTimestamp());
    }

    public function testMigrationCreatesExpectedIndexAndIsIdempotent(): void
    {
        $this->rollback();

        $migration = new Migration1780325388AddMediaFolderCreatedAtIndex();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'media', self::INDEX_NAME));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'media', self::INDEX_NAME, ['media_folder_id', 'created_at', 'id']));
    }

    private function rollback(): void
    {
        if (!TableHelper::indexExists($this->connection, 'media', self::INDEX_NAME)) {
            return;
        }

        if (!TableHelper::indexExists($this->connection, 'media', 'fk.media.media_folder_id')) {
            $this->connection->executeStatement('CREATE INDEX `fk.media.media_folder_id` ON `media` (`media_folder_id`)');
        }

        $this->connection->executeStatement('DROP INDEX `' . self::INDEX_NAME . '` ON `media`');
    }
}
