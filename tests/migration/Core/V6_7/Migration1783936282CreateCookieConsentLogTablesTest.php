<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1783936282CreateCookieConsentLogTables;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1783936282CreateCookieConsentLogTables::class)]
class Migration1783936282CreateCookieConsentLogTablesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1783936282CreateCookieConsentLogTables();
        static::assertSame(1783936282, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `cookie_consent_log`;');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `cookie_consent_config_version`;');

        $migration = new Migration1783936282CreateCookieConsentLogTables();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'cookie_consent_log'));
        static::assertTrue(TableHelper::tableExists($this->connection, 'cookie_consent_config_version'));

        $logColumns = array_column(TableHelper::getTable($this->connection, 'cookie_consent_log')->columns, 'name');
        static::assertEqualsCanonicalizing(
            ['id', 'sales_channel_id', 'language_id', 'consent_action', 'group_decisions', 'accepted_cookies', 'server_config_hash', 'rendered_config_hash', 'created_at', 'updated_at'],
            $logColumns
        );

        $configVersionColumns = array_column(TableHelper::getTable($this->connection, 'cookie_consent_config_version')->columns, 'name');
        static::assertEqualsCanonicalizing(
            ['id', 'config_hash', 'sales_channel_id', 'language_id', 'cookie_groups', 'created_at', 'updated_at'],
            $configVersionColumns
        );
    }
}
