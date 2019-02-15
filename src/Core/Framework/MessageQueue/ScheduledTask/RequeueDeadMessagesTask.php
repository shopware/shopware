<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask;

use Shopware\Core\Framework\ScheduledTask\ScheduledTaskInterface;

class RequeueDeadMessagesTask implements ScheduledTaskInterface
{
    /**
     * @var string
     */
    protected $taskId;

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function setTaskId(string $taskId): void
    {
        $this->taskId = $taskId;
    }

    public static function getTaskName(): string
    {
        return 'requeue_dead_messages';
    }

    public static function getDefaultInterval(): int
    {
        return 300; //every 5 min
    }
}
