<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1765287398AddConsentLogTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1765287398AddConsentLogTable::class)]
class Migration1765287398AddConsentLogTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1765287398AddConsentLogTable();
        static::assertSame(1765287398, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `consent_log`;');

        $migration = new Migration1765287398AddConsentLogTable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'consent_log'));

        $consentStateColumns = TableHelper::getTable($this->connection, 'consent_log')->columns;
        static::assertCount(4, $consentStateColumns);
    }
}
