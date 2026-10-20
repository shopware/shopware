<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1790667101AddAclRoleIdsToWebhook;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1790667101AddAclRoleIdsToWebhook::class)]
class Migration1790667101AddAclRoleIdsToWebhookTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1790667101, (new Migration1790667101AddAclRoleIdsToWebhook())->getCreationTimestamp());
    }

    public function testMigrationAddsTheColumn(): void
    {
        if (TableHelper::columnExists($this->connection, 'webhook', 'acl_role_ids')) {
            $this->connection->executeStatement('ALTER TABLE `webhook` DROP COLUMN `acl_role_ids`');
        }

        $migration = new Migration1790667101AddAclRoleIdsToWebhook();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook', 'acl_role_ids'));
    }
}
