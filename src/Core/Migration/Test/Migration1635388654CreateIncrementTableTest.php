<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1635388654CreateIncrementTable;

class Migration1635388654CreateIncrementTableTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->rollback();
    }

    public function testMigration(): void
    {
        $migration = new Migration1635388654CreateIncrementTable();
        $migration->update($this->connection);

        $schemaManager = $this->connection->getSchemaManager();
        $columns = $schemaManager->listTableColumns('increment');

        static::assertNotEmpty($columns);
        static::assertArrayHasKey('pool', $columns);
        static::assertArrayHasKey('cluster', $columns);
        static::assertArrayHasKey('`key`', $columns);
        static::assertArrayHasKey('count', $columns);
        static::assertArrayHasKey('created_at', $columns);
        static::assertArrayHasKey('updated_at', $columns);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `increment`');
    }
}
