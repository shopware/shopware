<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\ColumnExistsTrait;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1752499887UpdateAppRequestedPrivileges;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1752499887UpdateAppRequestedPrivileges::class)]
class Migration1752499887UpdateAppRequestedPrivilegesTest extends TestCase
{
    use ColumnExistsTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement(
                'ALTER TABLE `app` MODIFY COLUMN `requested_privileges` JSON NULL;'
            );
        } catch (\Throwable) {
        }
    }

    public function testMigration(): void
    {
        $migration = new Migration1752499887UpdateAppRequestedPrivileges();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $columns = $this->connection->createSchemaManager()->listTableColumns(AppDefinition::ENTITY_NAME);
        $requestedPrivilegesColumn = $columns['requested_privileges'];
        static::assertTrue($requestedPrivilegesColumn->getNotnull(), 'Column should be NOT NULL');
    }
}
