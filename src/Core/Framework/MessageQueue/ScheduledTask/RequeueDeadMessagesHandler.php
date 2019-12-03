<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\DeadMessage\RequeueDeadMessagesService;

class RequeueDeadMessagesHandler extends ScheduledTaskHandler
{
    /**
     * @var RequeueDeadMessagesService
     */
    private $requeueService;

    public function __construct(EntityRepositoryInterface $scheduledTaskRepository, RequeueDeadMessagesService $requeueService)
    {
        parent::__construct($scheduledTaskRepository);
        $this->requeueService = $requeueService;
    }

    public static function getHandledMessages(): iterable
    {
        return [
            RequeueDeadMessagesTask::class,
        ];
    }

    public function run(): void
    {
        $this->requeueService->requeue();
    }
}
