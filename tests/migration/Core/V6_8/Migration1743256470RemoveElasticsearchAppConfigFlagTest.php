<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_8\Migration1743256470RemoveElasticsearchAppConfigFlag;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1743256470RemoveElasticsearchAppConfigFlag::class)]
class Migration1743256470RemoveElasticsearchAppConfigFlagTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $storage = new MySQLKeyValueStorage($this->connection);

        $storage->set('ELASTIC_OPTIMIZE_FLAG', true);

        static::assertTrue($storage->has('ELASTIC_OPTIMIZE_FLAG'));
        $migration = new Migration1743256470RemoveElasticsearchAppConfigFlag();
        static::assertSame(1743256470, $migration->getCreationTimestamp());

        // make sure a migration can run multiple times without failing
        $migration->update($this->connection);
        $migration->update($this->connection);

        $storage->reset();
        static::assertFalse($storage->has('ELASTIC_OPTIMIZE_FLAG'));
    }
}
