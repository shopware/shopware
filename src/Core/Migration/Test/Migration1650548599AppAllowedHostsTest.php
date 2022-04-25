<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1650548599AppAllowedHosts;

class Migration1650548599AppAllowedHostsTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $container = $this->getContainer();
        $this->connection = $container->get(Connection::class);
    }

    public function testMigration(): void
    {
        $this->connection->executeStatement('ALTER TABLE `app` DROP COLUMN `allowed_hosts`');

        $migration = new Migration1650548599AppAllowedHosts();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $columns = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM app'), 'Field');
        static::assertTrue(\in_array('allowed_hosts', $columns, true));
    }
}
