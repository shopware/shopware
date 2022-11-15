<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\fixtures;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @internal
 */
final class DummyScheduledTaskHandler extends ScheduledTaskHandler
{
    private string $taskId;

    private bool $shouldThrowException;

    private bool $wasCalled = false;

    public function __construct(EntityRepository $scheduledTaskRepository, string $taskId, bool $shouldThrowException = false)
    {
        parent::__construct($scheduledTaskRepository);
        $this->taskId = $taskId;
        $this->shouldThrowException = $shouldThrowException;
    }

    /**
     * @return iterable<class-string<ScheduledTask>>
     */
    public static function getHandledMessages(): iterable
    {
        return [
            TestTask::class,
        ];
    }

    public function run(): void
    {
        $this->wasCalled = true;

        /** @var ScheduledTaskEntity $task */
        $task = $this->scheduledTaskRepository
            ->search(new Criteria([$this->taskId]), Context::createDefaultContext())
            ->get($this->taskId);

        if ($task->getStatus() !== ScheduledTaskDefinition::STATUS_RUNNING) {
            throw new \Exception('Scheduled Task was not marked as running.');
        }

        if ($this->shouldThrowException) {
            throw new \RuntimeException('This Exception should be thrown');
        }
    }

    public function wasCalled(): bool
    {
        return $this->wasCalled;
    }
}
