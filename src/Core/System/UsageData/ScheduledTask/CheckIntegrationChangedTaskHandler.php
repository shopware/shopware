<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\UsageData\Services\IntegrationChangedService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('merchant-services')]
#[AsMessageHandler(handles: CheckIntegrationChangedTask::class)]
final class CheckIntegrationChangedTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly IntegrationChangedService $integrationAppUrlChangedService,
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        $this->integrationAppUrlChangedService->checkAndHandleIntegrationChanged();
    }
}
