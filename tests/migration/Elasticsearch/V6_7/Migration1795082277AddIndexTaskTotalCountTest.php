<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Elasticsearch\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Migration\V6_7\Migration1795082277AddIndexTaskTotalCount;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1795082277AddIndexTaskTotalCount::class)]
class Migration1795082277AddIndexTaskTotalCountTest extends TestCase
{
    public function testMigrationPreservesExistingTasksAndIsIdempotent(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        if (TableHelper::columnExists($connection, 'elasticsearch_index_task', 'total_count')) {
            $connection->executeStatement('ALTER TABLE elasticsearch_index_task DROP COLUMN total_count');
        }

        $id = Uuid::randomBytes();
        $connection->insert('elasticsearch_index_task', [
            'id' => $id,
            '`entity`' => 'product',
            '`index`' => 'product_1',
            '`alias`' => 'product',
            'doc_count' => 25,
        ]);

        try {
            $migration = new Migration1795082277AddIndexTaskTotalCount();
            $migration->update($connection);
            $migration->update($connection);

            $task = $connection->fetchAssociative('SELECT doc_count, total_count FROM elasticsearch_index_task WHERE id = :id', ['id' => $id]);
            static::assertIsArray($task);
            static::assertSame(25, (int) $task['doc_count']);
            static::assertNull($task['total_count']);
        } finally {
            $connection->delete('elasticsearch_index_task', ['id' => $id]);
        }
    }
}
