<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1676367607RemoveIntegrationWriteAccessColumn;

/**
 * @internal
 */
#[CoversClass(Migration1676367607RemoveIntegrationWriteAccessColumn::class)]
class Migration1676367607RemoveIntegrationWriteAccessColumnTest extends TestCase
{
    public function testUpdateDestructiveRemovesColumn(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $existed = $this->columnExists($connection);

        if (!$existed) {
            $connection->executeStatement('
                ALTER TABLE `integration` ADD COLUMN `write_access` TINYINT(1) DEFAULT 0
            ');
        }

        $migration = new Migration1676367607RemoveIntegrationWriteAccessColumn();

        $migration->updateDestructive($connection);
        $migration->updateDestructive($connection);

        static::assertFalse($this->columnExists($connection));

        if ($existed) {
            $connection->executeStatement('
                ALTER TABLE `integration` ADD COLUMN `write_access` TINYINT(1) DEFAULT 0
            ');
        }
    }

    protected function columnExists(Connection $connection): bool
    {
        $exists = $connection->fetchOne(
            'SHOW COLUMNS FROM `integration` WHERE `Field` LIKE "write_access"',
        );

        return !empty($exists);
    }
}
