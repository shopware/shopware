<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry\ScheduledTaskOverdueGateway;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ScheduledTaskOverdueGatewayTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private ScheduledTaskOverdueGateway $gateway;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $this->gateway = new ScheduledTaskOverdueGateway($this->connection);
    }

    public function testCountsOnlyScheduledTasksBeforeTheGivenTime(): void
    {
        $now = new \DateTimeImmutable('2026-07-06 12:00:00');

        $this->createTasks([
            // overdue: scheduled and past $now
            ['status' => ScheduledTaskDefinition::STATUS_SCHEDULED, 'nextExecutionTime' => $now->modify('-1 minute')],
            ['status' => ScheduledTaskDefinition::STATUS_SCHEDULED, 'nextExecutionTime' => $now->modify('-1 hour')],
            // not overdue: due exactly at or after $now (strict comparison)
            ['status' => ScheduledTaskDefinition::STATUS_SCHEDULED, 'nextExecutionTime' => $now],
            ['status' => ScheduledTaskDefinition::STATUS_SCHEDULED, 'nextExecutionTime' => $now->modify('+1 hour')],
            // not overdue: past $now but not in 'scheduled' status
            ['status' => ScheduledTaskDefinition::STATUS_QUEUED, 'nextExecutionTime' => $now->modify('-1 minute')],
            ['status' => ScheduledTaskDefinition::STATUS_SKIPPED, 'nextExecutionTime' => $now->modify('-1 minute')],
            ['status' => ScheduledTaskDefinition::STATUS_INACTIVE, 'nextExecutionTime' => $now->modify('-1 minute')],
        ]);

        static::assertSame(2, $this->gateway->countOverdue($now));
    }

    public function testCountsZeroWithoutOverdueTasks(): void
    {
        $now = new \DateTimeImmutable('2026-07-06 12:00:00');

        $this->createTasks([
            ['status' => ScheduledTaskDefinition::STATUS_SCHEDULED, 'nextExecutionTime' => $now->modify('+1 hour')],
        ]);

        static::assertSame(0, $this->gateway->countOverdue($now));
    }

    /**
     * @param list<array{status: string, nextExecutionTime: \DateTimeInterface}> $tasks
     */
    private function createTasks(array $tasks): void
    {
        foreach ($tasks as $index => $task) {
            $this->connection->insert('scheduled_task', [
                'id' => Uuid::randomBytes(),
                'name' => 'test_' . $index,
                // unique per row (scheduled_task_class carries a unique constraint)
                'scheduled_task_class' => 'Test\\OverdueTask' . $index,
                'run_interval' => 300,
                'default_run_interval' => 300,
                'status' => $task['status'],
                'next_execution_time' => $task['nextExecutionTime']->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'created_at' => '2026-07-06 00:00:00.000',
            ]);
        }
    }
}
