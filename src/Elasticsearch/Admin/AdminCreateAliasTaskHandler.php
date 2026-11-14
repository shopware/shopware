<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Promotes admin indices whose indexing run finished to their alias. Queued runs return from
 * {@see AdminSearchRegistry::iterate()} long before the queue is drained, so the swap cannot happen there.
 *
 * @internal
 */
#[Package('inventory')]
#[AsMessageHandler(handles: AdminCreateAliasTask::class)]
final class AdminCreateAliasTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly AdminSearchRegistry $registry,
        private readonly AdminElasticsearchHelper $adminEsHelper,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        if (!$this->adminEsHelper->isEnabled()) {
            return;
        }

        try {
            $this->registry->swapFinishedAliases();
        } catch (\Throwable $e) {
            // catch exception - otherwise the task will never be called again
            $this->adminEsHelper->logAndThrowException($e);
        }
    }
}
