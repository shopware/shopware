<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_6\Migration1716968180AddAppSourceConfig;

/**
 * @internal
 */
#[CoversClass(Migration1716968180AddAppSourceConfig::class)]
class Migration1716968180AddAppSourceConfigTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1716968180, (new Migration1716968180AddAppSourceConfig())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->dropSourceConfigColumn();

        static::assertFalse(TableHelper::columnExists($this->connection, 'app', 'source_config'));

        $migration = new Migration1716968180AddAppSourceConfig();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'app', 'source_config'));
    }

    private function dropSourceConfigColumn(): void
    {
        try {
            $this->connection->executeStatement(
                'ALTER TABLE `app` DROP COLUMN `source_config`;'
            );
        } catch (\Throwable) {
        }
    }
}
