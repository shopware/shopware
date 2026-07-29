<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1771342632AddCorruptedMediaHandlerIndices;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1771342632AddCorruptedMediaHandlerIndices::class)]
class Migration1771342632AddCorruptedMediaHandlerIndicesTest extends TestCase
{
    private const INDEX_NAME = 'idx.media.uploaded_at_created_at_id';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1771342632, (new Migration1771342632AddCorruptedMediaHandlerIndices())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1771342632AddCorruptedMediaHandlerIndices();

        static::assertSame(1771342632, $migration->getCreationTimestamp());
    }

    public function testMigrationCreatesExpectedIndexAndIsIdempotent(): void
    {
        $this->rollback();

        $migration = new Migration1771342632AddCorruptedMediaHandlerIndices();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'media', self::INDEX_NAME));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'media', self::INDEX_NAME, ['uploaded_at', 'created_at', 'id']));
    }

    private function rollback(): void
    {
        if (!TableHelper::indexExists($this->connection, 'media', self::INDEX_NAME)) {
            return;
        }

        $this->connection->executeStatement('DROP INDEX `' . self::INDEX_NAME . '` ON `media`');
    }
}
