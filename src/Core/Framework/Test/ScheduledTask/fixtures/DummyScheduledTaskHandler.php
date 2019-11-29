<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ScheduledTask\fixtures;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\RequeueDeadMessagesTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class DummyScheduledTaskHandler extends ScheduledTaskHandler
{
    /**
     * @var string
     */
    private $taskId;

    /**
     * @var bool
     */
    private $shouldThrowException;

    /**
     * @var bool
     */
    private $wasCalled = false;

    public function __construct(EntityRepositoryInterface $scheduledTaskRepository, string $taskId, bool $shouldThrowException = false)
    {
        parent::__construct($scheduledTaskRepository);
        $this->taskId = $taskId;
        $this->shouldThrowException = $shouldThrowException;
    }

    public static function getHandledMessages(): iterable
    {
        return [
            RequeueDeadMessagesTask::class,
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
