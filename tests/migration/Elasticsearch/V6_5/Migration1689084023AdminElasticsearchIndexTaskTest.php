<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Elasticsearch\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Elasticsearch\Migration\V6_5\Migration1689084023AdminElasticsearchIndexTask;

/**
 * @internal
 */
#[CoversClass(Migration1689084023AdminElasticsearchIndexTask::class)]
class Migration1689084023AdminElasticsearchIndexTaskTest extends TestCase
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
        $migration = new Migration1689084023AdminElasticsearchIndexTask();
        $migration->update($this->connection);

        $schemaManager = $this->connection->createSchemaManager();
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
