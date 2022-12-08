<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\ScheduledTask;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\Command\RegisterScheduledTasksCommand;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class RegisterScheduledTaskTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testNoValidationErrors(): void
    {
        $taskRegistry = $this->createMock(TaskRegistry::class);
        $taskRegistry->expects(static::once())
            ->method('registerTasks');

        $commandTester = new CommandTester(new RegisterScheduledTasksCommand($taskRegistry));
        $commandTester->execute([]);
    }
}
