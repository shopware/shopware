<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1780315490AddMediaFileNameSortKey;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1780315490AddMediaFileNameSortKey::class)]
class Migration1780315490AddMediaFileNameSortKeyTest extends TestCase
{
    private const COLUMN_NAME = 'file_name_sort_key';

    private const INDEX_NAME = 'idx.media.media_folder_id_file_name_sort_key_id';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1780315490, (new Migration1780315490AddMediaFileNameSortKey())->getCreationTimestamp());
    }

    public function testMigrationCreatesExpectedColumnAndIndexAndIsIdempotent(): void
    {
        $this->rollback();

        $migration = new Migration1780315490AddMediaFileNameSortKey();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'media', self::COLUMN_NAME));
        static::assertSame('STORED GENERATED', $this->getColumnExtra(self::COLUMN_NAME));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'media', 'fk.media.media_folder_id', ['media_folder_id']));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'media', self::INDEX_NAME, ['media_folder_id', self::COLUMN_NAME, 'id']));
    }

    public function testGeneratedSortKeyUsesFileNamePrefix(): void
    {
        $this->rollback();
        (new Migration1780315490AddMediaFileNameSortKey())->update($this->connection);

        $mediaId = Uuid::randomBytes();

        try {
            $this->connection->insert('media', [
                'id' => $mediaId,
                'file_name' => str_repeat('a', 300),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            static::assertSame(255, (int) $this->connection->fetchOne(
                'SELECT CHAR_LENGTH(`file_name_sort_key`) FROM `media` WHERE `id` = :id',
                ['id' => $mediaId]
            ));
        } finally {
            $this->connection->delete('media', ['id' => $mediaId]);
        }
    }

    private function rollback(): void
    {
        if (TableHelper::indexExists($this->connection, 'media', self::INDEX_NAME)) {
            if (!TableHelper::indexExists($this->connection, 'media', 'fk.media.media_folder_id')) {
                $this->connection->executeStatement('CREATE INDEX `fk.media.media_folder_id` ON `media` (`media_folder_id`)');
            }

            $this->connection->executeStatement('DROP INDEX `' . self::INDEX_NAME . '` ON `media`');
        }

        if (TableHelper::columnExists($this->connection, 'media', self::COLUMN_NAME)) {
            $this->connection->executeStatement('ALTER TABLE `media` DROP COLUMN `' . self::COLUMN_NAME . '`');
        }
    }

    private function getColumnExtra(string $columnName): string
    {
        return (string) $this->connection->fetchOne(
            <<<'SQL'
            SELECT `EXTRA`
            FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :database
                AND `TABLE_NAME` = 'media'
                AND `COLUMN_NAME` = :column
            SQL,
            [
                'database' => $this->connection->getDatabase(),
                'column' => $columnName,
            ]
        );
    }
}
