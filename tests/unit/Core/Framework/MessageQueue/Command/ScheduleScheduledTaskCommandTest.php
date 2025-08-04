<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Command\ScheduleScheduledTaskCommand;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScheduleScheduledTaskCommand::class)]
class ScheduleScheduledTaskCommandTest extends TestCase
{
    private MockObject&TaskRegistry $taskRegistry;

    private ScheduleScheduledTaskCommand $command;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->taskRegistry = $this->createMock(TaskRegistry::class);
        $this->command = new ScheduleScheduledTaskCommand($this->taskRegistry);
        $this->commandTester = new CommandTester($this->command);
    }

    public function testSuccessfulScheduling(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('scheduleTask')
            ->with('test.task', false, false, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_SCHEDULED);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Scheduled task "test.task" was scheduled.', $this->getCommandDisplay());
    }

    public function testSuccessfulSchedulingWithSkippedStatus(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('scheduleTask')
            ->with('test.task', false, false, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_SKIPPED);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Scheduled task "test.task" was scheduled.', $this->getCommandDisplay());
    }

    public function testSchedulingWithImmediatelyOption(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('scheduleTask')
            ->with('test.task', true, false, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_SCHEDULED);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
            '--immediately' => true,
        ]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Scheduled task "test.task" was scheduled to now.', $this->getCommandDisplay());
    }

    public function testSchedulingWithForceOption(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('scheduleTask')
            ->with('test.task', false, true, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_SCHEDULED);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
            '--force' => true,
        ]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Scheduled task "test.task" was scheduled.', $this->getCommandDisplay());
    }

    public function testSchedulingWithBothOptions(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('scheduleTask')
            ->with('test.task', true, true, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_SCHEDULED);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
            '--immediately' => true,
            '--force' => true,
        ]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Scheduled task "test.task" was scheduled to now.', $this->getCommandDisplay());
    }

    public function testFailureWhenTaskIsQueued(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('scheduleTask')
            ->with('test.task', false, false, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_QUEUED);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('Scheduled task "test.task" is marked as currently running, use --force to force scheduling.', $this->getCommandDisplay());
    }

    public function testFailureWhenTaskIsRunning(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('scheduleTask')
            ->with('test.task', false, false, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_RUNNING);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('Scheduled task "test.task" is marked as currently running, use --force to force scheduling.', $this->getCommandDisplay());
    }

    public function testFailureWhenTaskIsFailed(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('scheduleTask')
            ->with('test.task', false, false, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_FAILED);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('Could not schedule task "test.task", task has unexpected state: failed', $this->getCommandDisplay());
    }

    public function testFailureWhenTaskIsInactive(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('scheduleTask')
            ->with('test.task', false, false, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_INACTIVE);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('Could not schedule task "test.task", task has unexpected state: inactive', $this->getCommandDisplay());
    }

    public function testFailureWithUnknownStatus(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('scheduleTask')
            ->with('test.task', false, false, static::isInstanceOf(Context::class))
            ->willReturn('unknown_status');

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('Could not schedule task "test.task", task has unexpected state: unknown_status', $this->getCommandDisplay());
    }

    private function getCommandDisplay(): string
    {
        return (string) str_replace(["\n\r", "\n", "\r"], '', $this->commandTester->getDisplay(true));
    }
}
