<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1752499887UpdateAppRequestedPrivileges;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1752499887UpdateAppRequestedPrivileges::class)]
class Migration1752499887UpdateAppRequestedPrivilegesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1752499887, (new Migration1752499887UpdateAppRequestedPrivileges())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->makeRequestedPrivilegesNullable();

        $migration = new Migration1752499887UpdateAppRequestedPrivileges();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $requestedPrivilegesColumn = TableHelper::getColumnOfTable($this->connection, AppDefinition::ENTITY_NAME, 'requested_privileges');
        static::assertTrue($requestedPrivilegesColumn->isNotNull, 'Column should be NOT NULL');
    }

    private function makeRequestedPrivilegesNullable(): void
    {
        try {
            $this->connection->executeStatement(
                'ALTER TABLE `app` MODIFY COLUMN `requested_privileges` JSON NULL;'
            );
        } catch (\Throwable) {
        }
    }
}
