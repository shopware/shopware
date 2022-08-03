<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1639122665AddCustomEntities;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_4\Migration1639122665AddCustomEntities
 */
class Migration1639122665AddCustomEntitiesTest extends TestCase
{
    public function testExecuteMultipleTimes(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->executeStatement('DROP TABLE `custom_entity`');

        $migration = new Migration1639122665AddCustomEntities();
        $migration->update($connection);

        $migration = new Migration1639122665AddCustomEntities();
        $migration->update($connection);

        $e = null;

        try {
            $connection->fetchOne('SELECT id FROM custom_entity');
        } catch (Exception $e) {
        }

        static::assertNull($e);
    }

    public function testTablesIsPresent(): void
    {
        $columns = array_column(KernelLifecycleManager::getConnection()->fetchAllAssociative('SHOW COLUMNS FROM custom_entity'), 'Field');

        static::assertContains('id', $columns);
        static::assertContains('name', $columns);
        static::assertContains('fields', $columns);
        static::assertContains('app_id', $columns);
        static::assertContains('created_at', $columns);
        static::assertContains('updated_at', $columns);
    }
}
