<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1667731399AdminElasticsearchIndexTask;

/**
 * @package core
 *
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1667731399AdminElasticsearchIndexTask
 */
class Migration1667731399AdminElasticsearchIndexTaskTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->rollback();
    }

    public function testMigration(): void
    {
        $migration = new Migration1667731399AdminElasticsearchIndexTask();
        $migration->update($this->connection);

        $schemaManager = $this->connection->getSchemaManager();
        $columns = $schemaManager->listTableColumns('admin_elasticsearch_index_task');

        static::assertNotEmpty($columns);
        static::assertArrayHasKey('id', $columns);
        static::assertArrayHasKey('`index`', $columns);
        static::assertArrayHasKey('alias', $columns);
        static::assertArrayHasKey('entity', $columns);
        static::assertArrayHasKey('doc_count', $columns);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `admin_elasticsearch_index_task`');
    }
}
