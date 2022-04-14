<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1639122665AddCustomEntities;

class Migration1639122665AddCustomEntitiesTest extends TestCase
{
    use KernelTestBehaviour;

    public function testExecuteMultipleTimes(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
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
        $columns = array_column($this->getContainer()->get(Connection::class)->fetchAllAssociative('SHOW COLUMNS FROM custom_entity'), 'Field');

        static::assertContains('id', $columns);
        static::assertContains('name', $columns);
        static::assertContains('fields', $columns);
        static::assertContains('app_id', $columns);
        static::assertContains('created_at', $columns);
        static::assertContains('updated_at', $columns);
    }
}
