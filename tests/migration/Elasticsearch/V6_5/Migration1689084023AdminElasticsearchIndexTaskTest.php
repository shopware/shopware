<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Elasticsearch\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
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

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1689084023, (new Migration1689084023AdminElasticsearchIndexTask())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $migration = new Migration1689084023AdminElasticsearchIndexTask();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'admin_elasticsearch_index_task', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'admin_elasticsearch_index_task', 'index'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'admin_elasticsearch_index_task', 'alias'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'admin_elasticsearch_index_task', 'entity'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'admin_elasticsearch_index_task', 'doc_count'));
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `admin_elasticsearch_index_task`');
    }
}
