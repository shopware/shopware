<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('data-services')]
#[AsMessageHandler(handles: CollectEntityDataTask::class)]
final class CollectEntityDataTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly EntityDispatchService $entityDispatchService,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $this->entityDispatchService->dispatchCollectEntityDataMessage();
    }
}
