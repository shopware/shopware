<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\ColumnExistsTrait;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1740563553AddAppRequestedPrivileges;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1740563553AddAppRequestedPrivileges::class)]
class Migration1740563553AddAppRequestedPrivilegesTest extends TestCase
{
    use ColumnExistsTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement(
                'ALTER TABLE `app` DROP COLUMN `requested_privileges`;'
            );
        } catch (\Throwable) {
        }
    }

    public function testMigration(): void
    {
        static::assertFalse($this->columnExists($this->connection, 'app', 'requested_privileges'));

        $migration = new Migration1740563553AddAppRequestedPrivileges();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->columnExists($this->connection, 'app', 'requested_privileges'));
    }
}
