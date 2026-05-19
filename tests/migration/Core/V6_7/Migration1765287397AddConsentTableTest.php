<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1765287397AddConsentTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1765287397AddConsentTable::class)]
class Migration1765287397AddConsentTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1765287397AddConsentTable();
        static::assertSame(1765287397, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `consent_state`;');

        $migration = new Migration1765287397AddConsentTable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'consent_state'));

        $consentStateColumns = TableHelper::getTable($this->connection, 'consent_state')->columns;
        static::assertCount(6, $consentStateColumns);
    }
}
