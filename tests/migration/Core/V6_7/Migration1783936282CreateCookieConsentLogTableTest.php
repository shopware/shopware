<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1783936282CreateCookieConsentLogTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1783936282CreateCookieConsentLogTable::class)]
class Migration1783936282CreateCookieConsentLogTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1783936282, (new Migration1783936282CreateCookieConsentLogTable())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `cookie_consent_log`;');

        $migration = new Migration1783936282CreateCookieConsentLogTable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'cookie_consent_log'));
        static::assertEqualsCanonicalizing(
            ['id', 'consent_id', 'consent_action', 'group_decisions', 'accepted_cookies', 'config_hash', 'sales_channel_id', 'language_id', 'created_at'],
            array_column(TableHelper::getTable($this->connection, 'cookie_consent_log')->columns, 'name')
        );
    }
}
