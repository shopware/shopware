<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry\ScheduledTaskHealthGateway;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class ScheduledTaskHealthGatewayTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private ScheduledTaskHealthGateway $gateway;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $this->gateway = new ScheduledTaskHealthGateway($this->connection);
    }

    public function testMaxLatenessSecondsUsesOldestDueScheduledOrSkippedTask(): void
    {
        $now = new \DateTimeImmutable('2026-07-06 12:00:00');

        $this->createTasks([
            // due: scheduled/skipped and past $now — the oldest carries the max lateness
            ['status' => ScheduledTaskDefinition::STATUS_SCHEDULED, 'nextExecutionTime' => $now->modify('-1 minute')],
            ['status' => ScheduledTaskDefinition::STATUS_SKIPPED, 'nextExecutionTime' => $now->modify('-1 hour')],
            // not due: at or after $now (strict comparison)
            ['status' => ScheduledTaskDefinition::STATUS_SCHEDULED, 'nextExecutionTime' => $now],
            ['status' => ScheduledTaskDefinition::STATUS_SCHEDULED, 'nextExecutionTime' => $now->modify('+1 hour')],
            // ignored: past $now but not awaiting the scheduler
            ['status' => ScheduledTaskDefinition::STATUS_QUEUED, 'nextExecutionTime' => $now->modify('-2 hours')],
            ['status' => ScheduledTaskDefinition::STATUS_RUNNING, 'nextExecutionTime' => $now->modify('-3 hours')],
            ['status' => ScheduledTaskDefinition::STATUS_FAILED, 'nextExecutionTime' => $now->modify('-4 hours')],
            ['status' => ScheduledTaskDefinition::STATUS_INACTIVE, 'nextExecutionTime' => $now->modify('-5 hours')],
        ]);

        static::assertSame(3600, $this->gateway->getMaxLatenessSeconds($now));
    }

    public function testMaxLatenessSecondsIsZeroWithoutDueTasks(): void
    {
        $now = new \DateTimeImmutable('2026-07-06 12:00:00');

        $this->createTasks([
            ['status' => ScheduledTaskDefinition::STATUS_SCHEDULED, 'nextExecutionTime' => $now->modify('+1 hour')],
            ['status' => ScheduledTaskDefinition::STATUS_FAILED, 'nextExecutionTime' => $now->modify('-1 hour')],
        ]);

        static::assertSame(0, $this->gateway->getMaxLatenessSeconds($now));
    }

    public function testContFailed(): void
    {
        $now = new \DateTimeImmutable('2026-07-06 12:00:00');

        $this->createTasks([
            ['status' => ScheduledTaskDefinition::STATUS_FAILED, 'nextExecutionTime' => $now],
            ['status' => ScheduledTaskDefinition::STATUS_FAILED, 'nextExecutionTime' => $now->modify('-1 hour')],
            ['status' => ScheduledTaskDefinition::STATUS_SCHEDULED, 'nextExecutionTime' => $now->modify('-1 hour')],
            ['status' => ScheduledTaskDefinition::STATUS_QUEUED, 'nextExecutionTime' => $now],
        ]);

        static::assertSame(2, $this->gateway->countFailed());
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
                'scheduled_task_class' => 'Test\\HealthTask' . $index,
                'run_interval' => 300,
                'default_run_interval' => 300,
                'status' => $task['status'],
                'next_execution_time' => $task['nextExecutionTime']->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'created_at' => '2026-07-06 00:00:00.000',
            ]);
        }
    }
}
