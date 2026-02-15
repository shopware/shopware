<?php declare(strict_types=1);

namespace Shopware\Core\Service\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Service\LifecycleManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: InstallServicesTask::class)]
final class InstallServicesTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        int $maintenanceWindowStart,
        array $maintenanceScheduledTasks,
        private readonly LifecycleManager $manager,
    ) {
        parent::__construct($repository, $logger, $maintenanceWindowStart, $maintenanceScheduledTasks);
    }

    public function run(): void
    {
        $this->manager->install(Context::createCLIContext());
    }
}
