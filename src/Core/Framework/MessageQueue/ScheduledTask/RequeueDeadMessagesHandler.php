<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\MessageQueue\DeadMessage\RequeueDeadMessagesService;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:remove-subscriber - Will be removed, as we use the default symfony retry mechanism
 */
class RequeueDeadMessagesHandler extends ScheduledTaskHandler
{
    /**
     * @var RequeueDeadMessagesService
     */
    private $requeueService;

    /**
     * @internal
     */
    public function __construct(EntityRepository $scheduledTaskRepository, RequeueDeadMessagesService $requeueService)
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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $this->requeueService->requeue();
    }
}
