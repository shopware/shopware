<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\Command\ListScheduledTaskCommand;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ListScheduledTaskCommand::class)]
class ListScheduledTaskCommandTest extends TestCase
{
    public function testListTasks(): void
    {
        $entity = new ScheduledTaskEntity();
        $entity->setId('test');
        $entity->setName('TestTask.ID');
        $entity->setNextExecutionTime(new \DateTime());
        $entity->setRunInterval(100);
        $entity->setStatus(ScheduledTaskDefinition::STATUS_QUEUED);

        $taskRegistry = $this->createMock(TaskRegistry::class);
        $taskRegistry
            ->method('getAllTasks')
            ->willReturn(new ScheduledTaskCollection([$entity]));

        $command = new ListScheduledTaskCommand($taskRegistry);

        $tester = new CommandTester($command);
        $tester->execute([]);

        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('Next execution', $tester->getDisplay());
        static::assertStringContainsString('Last execution', $tester->getDisplay());
        static::assertStringContainsString('Run interval', $tester->getDisplay());
        static::assertStringContainsString('Status', $tester->getDisplay());
        static::assertStringContainsString('TestTask.ID', $tester->getDisplay());
    }
}
