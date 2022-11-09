<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1667983492UpdateQueuedTasksToSkipped;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTask;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1667983492UpdateQueuedTasksToSkipped
 */
class Migration1667983492UpdateQueuedTasksToSkippedTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->beginTransaction();
        $this->prepare();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testUpdateQueueTasksToSkipped(): void
    {
        $migration = new Migration1667983492UpdateQueuedTasksToSkipped();
        $migration->update($this->connection);

        $statuses = array_unique($this->connection->fetchFirstColumn('SELECT status FROM scheduled_task'));

        static::assertCount(1, $statuses);
        static::assertEquals(ScheduledTaskDefinition::STATUS_SKIPPED, $statuses[0]);
    }

    private function prepare(): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('scheduled_task', [
            'id' => Uuid::randomBytes(),
            'name' => InvalidateCacheTask::getTaskName(),
            'scheduled_task_class' => InvalidateCacheTask::class,
            'run_interval' => InvalidateCacheTask::getDefaultInterval(),
            'created_at' => $now,
            'next_execution_time' => $now,
            'status' => ScheduledTaskDefinition::STATUS_QUEUED,
        ]);

        $this->connection->insert('scheduled_task', [
            'id' => Uuid::randomBytes(),
            'name' => CreateAliasTask::getTaskName(),
            'scheduled_task_class' => CreateAliasTask::class,
            'run_interval' => InvalidateCacheTask::getDefaultInterval(),
            'created_at' => $now,
            'next_execution_time' => $now,
            'status' => ScheduledTaskDefinition::STATUS_QUEUED,
        ]);
    }
}
