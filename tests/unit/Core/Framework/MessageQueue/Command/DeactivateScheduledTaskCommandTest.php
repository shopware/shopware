<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Command\DeactivateScheduledTaskCommand;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeactivateScheduledTaskCommand::class)]
class DeactivateScheduledTaskCommandTest extends TestCase
{
    private MockObject&TaskRegistry $taskRegistry;

    private DeactivateScheduledTaskCommand $command;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->taskRegistry = $this->createMock(TaskRegistry::class);
        $this->command = new DeactivateScheduledTaskCommand($this->taskRegistry);
        $this->commandTester = new CommandTester($this->command);
    }

    public function testSuccessfulDeactivation(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('deactivateTask')
            ->with('test.task', false, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_INACTIVE);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsStringIgnoringLineEndings($this->compressString('Scheduled task "test.task" was deactivated.'), $this->getCompressedCommandDisplay());
    }

    public function testDeactivationWithForceOption(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('deactivateTask')
            ->with('test.task', true, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_INACTIVE);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
            '--force' => true,
        ]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsStringIgnoringLineEndings($this->compressString('Scheduled task "test.task" was deactivated.'), $this->getCompressedCommandDisplay());
    }

    public function testFailureWhenTaskIsQueued(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('deactivateTask')
            ->with('test.task', false, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_QUEUED);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsStringIgnoringLineEndings($this->compressString('Scheduled task "test.task" is marked as currently "queued", use --force to force deactivation.'), $this->getCompressedCommandDisplay());
    }

    public function testFailureWhenTaskIsRunning(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('deactivateTask')
            ->with('test.task', false, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_RUNNING);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsStringIgnoringLineEndings($this->compressString('Scheduled task "test.task" is marked as currently "running", use --force to force deactivation.'), $this->getCompressedCommandDisplay());
    }

    public function testFailureWhenTaskIsFailed(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('deactivateTask')
            ->with('test.task', false, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_FAILED);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsStringIgnoringLineEndings($this->compressString('Could not deactivate task "test.task", task has unexpected state: failed'), $this->getCompressedCommandDisplay());
    }

    public function testFailureWhenTaskIsScheduled(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('deactivateTask')
            ->with('test.task', false, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_SCHEDULED);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsStringIgnoringLineEndings($this->compressString('Could not deactivate task "test.task", task has unexpected state: scheduled'), $this->getCompressedCommandDisplay());
    }

    public function testFailureWhenTaskIsSkipped(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('deactivateTask')
            ->with('test.task', false, static::isInstanceOf(Context::class))
            ->willReturn(ScheduledTaskDefinition::STATUS_SKIPPED);

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsStringIgnoringLineEndings($this->compressString('Could not deactivate task "test.task", task has unexpected state: skipped'), $this->getCompressedCommandDisplay());
    }

    public function testFailureWithUnknownStatus(): void
    {
        $this->taskRegistry
            ->expects($this->once())
            ->method('deactivateTask')
            ->with('test.task', false, static::isInstanceOf(Context::class))
            ->willReturn('unknown_status');

        $exitCode = $this->commandTester->execute([
            'taskName' => 'test.task',
        ]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsStringIgnoringLineEndings($this->compressString('Could not deactivate task "test.task", task has unexpected state: unknown_status'), $this->getCompressedCommandDisplay());
    }

    private function compressString(string $string): string
    {
        return (string) str_replace(' ', '', $string);
    }

    private function getCompressedCommandDisplay(): string
    {
        return (string) str_replace(["\n\r", "\n", "\r", ' '], '', $this->commandTester->getDisplay(true));
    }
}
