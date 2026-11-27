<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1795774112AddAiOriginToMedia;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1795774112AddAiOriginToMedia::class)]
class Migration1795774112AddAiOriginToMediaTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1795774112, (new Migration1795774112AddAiOriginToMedia())->getCreationTimestamp());
    }

    public function testAddsNullableAiOriginColumnIdempotently(): void
    {
        if (TableHelper::columnExists($this->connection, 'media', 'ai_origin')) {
            $this->connection->executeStatement('ALTER TABLE `media` DROP COLUMN `ai_origin`');
        }

        $migration = new Migration1795774112AddAiOriginToMedia();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'media', 'ai_origin'));
        static::assertFalse(TableHelper::getColumnOfTable($this->connection, 'media', 'ai_origin')->isNotNull);
    }
}
