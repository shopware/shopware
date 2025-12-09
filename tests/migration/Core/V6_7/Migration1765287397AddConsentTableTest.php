<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
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
        $this->connection->executeStatement('DROP TABLE IF EXISTS `consent`;');

        $sm = $this->connection->createSchemaManager();
        static::assertFalse($sm->tablesExist(['consent']));

        $migration = new Migration1765287397AddConsentTable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($sm->tablesExist(['consent']));

        $cols = $sm->listTableColumns('consent');
        static::assertCount(5, $cols);
    }

    public function testMigrationCreatesDefaultConsents(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `consent`;');

        $migration = new Migration1765287397AddConsentTable();
        $migration->update($this->connection);

        $consents = $this->connection->fetchAllAssociative('SELECT name, storage FROM consent ORDER BY name');

        static::assertCount(2, $consents);
        static::assertSame('backend_data_consent', $consents[0]['name']);
        static::assertSame('global', $consents[0]['storage']);
        static::assertSame('tracking_consent', $consents[1]['name']);
        static::assertSame('admin_user', $consents[1]['storage']);
    }
}
